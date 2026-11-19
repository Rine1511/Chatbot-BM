<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_and_vector_defaults_match_technical_decisions_and_consistent_dimensions(): void
    {
        $this->assertSame('gemini', config('ai.llm.driver'));
        $this->assertSame('gemini-2.5-flash', config('ai.llm.model'));
        $this->assertSame('gemini', config('ai.embedding.driver'));
        $this->assertSame('gemini-embedding-001', config('ai.embedding.model'));
        $this->assertSame(768, config('ai.embedding.dimensions'));
        $this->assertSame('qdrant', config('ai.vector.driver'));
        $this->assertSame('mysql', config('ai.vector.fallback_driver'));
        $this->assertSame(
            config('ai.embedding.dimensions'),
            config('ai.vector.qdrant.vector_size')
        );
        $this->assertSame('Cosine', config('ai.vector.qdrant.distance'));
        $this->assertArrayHasKey('openai_compatible', config('ai.providers'));
    }

    public function test_cors_session_security_and_sibbm_configurations_are_loaded(): void
    {
        $this->assertContains('https://bahrulmaghfirohmalang.or.id', config('cors.allowed_origins'));
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertIsBool(config('sibbm.enabled'));
        $this->assertIsInt(config('sibbm.timeout'));
    }

    public function test_application_runs_cleanly_even_when_qdrant_endpoint_is_unavailable(): void
    {
        config()->set('ai.vector.driver', 'qdrant');
        config()->set('ai.vector.qdrant.host', 'http://127.0.0.1:19999');

        $admin = User::factory()->superAdmin()->create();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('bm_knowledge_chunks');
    }
}
