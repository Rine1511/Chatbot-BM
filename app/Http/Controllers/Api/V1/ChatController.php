<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EmbeddingProviderException;
use App\Exceptions\LlmProviderException;
use App\Exceptions\VectorStoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Http\Requests\Api\StartConversationRequest;
use App\Models\Conversation;
use App\Services\Chat\RagService;
use App\Support\SecretRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly RagService $ragService,
    ) {}

    public function startConversation(StartConversationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $originDomain = $this->resolveOriginDomain(
            $validated['origin_domain'] ?? null,
            $request->headers->get('Origin') ?? $request->headers->get('Referer')
        );

        $rawVisitor = trim((string) ($validated['visitor_id'] ?? ''));
        $visitorMaterial = $rawVisitor !== ''
            ? $rawVisitor
            : ($request->ip().'|'.substr((string) $request->userAgent(), 0, 200));

        $visitorHash = hash('sha256', $visitorMaterial);
        $sessionToken = bin2hex(random_bytes(32));
        $now = now();

        $conversation = DB::transaction(static function () use ($sessionToken, $visitorHash, $originDomain, $now): Conversation {
            return Conversation::query()->create([
                'session_token' => $sessionToken,
                'visitor_hash' => $visitorHash,
                'origin_domain' => $originDomain,
                'started_at' => $now,
                'last_message_at' => $now,
                'message_count' => 0,
            ]);
        });

        $meta = $this->ragService->publicMetadata();

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'session_token' => $conversation->session_token,
                'chatbot_name' => $meta['chatbot_name'],
                'welcome_message' => $meta['welcome_message'],
                'origin_domain' => $conversation->origin_domain,
                'started_at' => $conversation->started_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function showConversation(Request $request, string $sessionToken): JsonResponse
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $sessionToken)) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'INVALID_SESSION_TOKEN',
                'message' => 'Format token sesi percakapan tidak valid.',
            ], 422);
        }

        $conversation = Conversation::query()
            ->where('session_token', strtolower($sessionToken))
            ->first();

        if (! $conversation) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'CONVERSATION_NOT_FOUND',
                'message' => 'Sesi percakapan tidak ditemukan.',
            ], 404);
        }

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(static fn ($msg): array => [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'content' => $msg->content,
                'is_fallback' => (bool) $msg->is_fallback,
                'matched_sources' => $msg->matched_sources ?? [],
                'response_time_ms' => $msg->response_time_ms,
                'created_at' => $msg->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation_id' => $conversation->id,
                'session_token' => $conversation->session_token,
                'message_count' => $conversation->message_count,
                'started_at' => $conversation->started_at?->toIso8601String(),
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'messages' => $messages,
            ],
        ]);
    }

    public function sendMessage(SendChatMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sessionToken = strtolower((string) $validated['session_token']);

        $conversation = Conversation::query()
            ->where('session_token', $sessionToken)
            ->first();

        if (! $conversation) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'CONVERSATION_NOT_FOUND',
                'message' => 'Sesi percakapan tidak ditemukan. Silakan mulai percakapan baru.',
            ], 404);
        }

        $filters = [];
        if (! empty($validated['category_slug'])) {
            $filters['category_slug'] = $validated['category_slug'];
        }

        try {
            $result = $this->ragService->reply($conversation, (string) $validated['message'], $filters);
        } catch (EmbeddingProviderException|LlmProviderException|VectorStoreException $e) {
            $safeLog = SecretRedactor::redact($e->getMessage());
            Log::error("Layanan AI/Vector gagal pada percakapan #{$conversation->id}: {$safeLog}");

            return response()->json([
                'status' => 'error',
                'error_code' => 'AI_SERVICE_UNAVAILABLE',
                'message' => 'Layanan asisten virtual sedang mengalami gangguan sementara. Silakan coba beberapa saat lagi.',
            ], 503);
        } catch (Throwable $e) {
            $safeLog = SecretRedactor::redact($e->getMessage());
            Log::error("Kesalahan tak terduga saat memproses pesan percakapan #{$conversation->id}: {$safeLog}");

            return response()->json([
                'status' => 'error',
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan sistem saat memproses pesan Anda. Silakan coba kembali.',
            ], 500);
        }

        $updatedConversation = $result['conversation'];
        $userMessage = $result['user_message'];
        $assistantMessage = $result['assistant_message'];

        return response()->json([
            'status' => 'success',
            'data' => [
                'conversation' => [
                    'id' => $updatedConversation->id,
                    'session_token' => $updatedConversation->session_token,
                    'message_count' => $updatedConversation->message_count,
                    'last_message_at' => $updatedConversation->last_message_at?->toIso8601String(),
                ],
                'user_message' => [
                    'id' => $userMessage->id,
                    'sender_type' => $userMessage->sender_type,
                    'content' => $userMessage->content,
                    'created_at' => $userMessage->created_at?->toIso8601String(),
                ],
                'assistant_message' => [
                    'id' => $assistantMessage->id,
                    'sender_type' => $assistantMessage->sender_type,
                    'content' => $assistantMessage->content,
                    'is_fallback' => (bool) $assistantMessage->is_fallback,
                    'matched_sources' => $result['matched_sources'],
                    'response_time_ms' => $assistantMessage->response_time_ms,
                    'created_at' => $assistantMessage->created_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    private function resolveOriginDomain(?string $inputDomain, ?string $headerUrl): ?string
    {
        $candidate = trim((string) ($inputDomain ?: $headerUrl));
        if ($candidate === '') {
            return null;
        }

        if (str_contains($candidate, '://')) {
            $host = parse_url($candidate, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return Str::limit(strtolower($host), 255, '');
            }
        }

        $clean = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $candidate) ?? '';

        return $clean !== '' ? Str::limit(strtolower($clean), 255, '') : null;
    }
}
