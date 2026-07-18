<?php

namespace Tests\Feature;

use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\ErpSetting;
use App\Models\User;
use App\Services\ErpChatbot\ChatbotLlmIntentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Tests\TestCase;

class ChatbotLlmSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_rules_page_includes_llm_settings(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        ErpSetting::query()->create([
            'app_name' => 'OCN ERP Suite',
            'chatbot_llm_enabled' => true,
            'chatbot_llm_api_url' => ChatbotLlmIntentClassifier::DEFAULT_API_URL,
            'chatbot_llm_model' => 'deepseek-chat',
            'chatbot_llm_api_key' => 'sk-test-key-1234',
        ]);

        $this->actingAs($user)
            ->get(route('erp.admin.parser-rules'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Admin/ParserRules')
                ->where('llmSettings.enabled', true)
                ->where('llmSettings.api_key_set', true)
                ->where('llmSettings.model', 'deepseek-chat')
            );
    }

    public function test_admin_can_save_llm_settings_without_resending_key(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        ErpSetting::query()->create([
            'app_name' => 'OCN ERP Suite',
            'chatbot_llm_enabled' => false,
            'chatbot_llm_api_url' => ChatbotLlmIntentClassifier::DEFAULT_API_URL,
            'chatbot_llm_model' => 'deepseek-chat',
            'chatbot_llm_api_key' => 'sk-existing-key',
        ]);

        $this->actingAs($user)
            ->patch(route('erp.admin.parser-rules.llm.update'), [
                'chatbot_llm_enabled' => true,
                'chatbot_llm_api_url' => 'https://api.deepseek.com/v1/chat/completions',
                'chatbot_llm_model' => 'deepseek-chat',
                'chatbot_llm_api_key' => '',
                'clear_api_key' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $setting = ErpSetting::query()->firstOrFail();
        $this->assertTrue($setting->chatbot_llm_enabled);
        $this->assertSame('sk-existing-key', $setting->chatbot_llm_api_key);
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
            RoleOrPermissionMiddleware::class,
        ]);
    }
}
