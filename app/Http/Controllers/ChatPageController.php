<?php

namespace App\Http\Controllers;

use App\Models\FaqCategory;
use App\Services\Chat\RagService;
use Illuminate\View\View;

class ChatPageController extends Controller
{
    public function index(RagService $ragService): View
    {
        $meta = $ragService->publicMetadata();

        $categories = FaqCategory::query()
            ->where('is_active', true)
            ->ordered()
            ->get(['name', 'slug', 'description']);

        return view('chat.index', [
            'chatbotName' => $meta['chatbot_name'],
            'welcomeMessage' => $meta['welcome_message'],
            'categories' => $categories,
        ]);
    }
}
