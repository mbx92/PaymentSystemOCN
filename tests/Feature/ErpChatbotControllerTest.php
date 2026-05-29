<?php

namespace Tests\Feature;

use App\Models\ErpChatParserRule;
use App\Models\MasterProduct;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Project;
use App\Models\ProjectPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpChatbotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_can_follow_up_on_product_context_for_price_lookup(): void
    {
        $user = User::factory()->create();

        ErpChatParserRule::query()->create([
            'name' => 'Stock Lookup',
            'intent_key' => 'stock_lookup',
            'keywords' => ['stok'],
            'match_mode' => 'and',
            'priority' => 1,
            'is_active' => true,
        ]);

        MasterProduct::query()->create([
            'sku' => 'KBL-001',
            'name' => 'Kabel LAN',
            'category' => 'Kabel',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 25000,
            'stock' => 12,
            'min_stock' => 3,
            'low_stock_alert_enabled' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'stok kabel lan',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'stock_lookup')
            ->assertJsonPath('answer', "**Kabel LAN**\nStok: 12 pcs\n\n💡 Tanya lagi: \"harganya?\" atau \"detailnya?\"");

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'harganya?',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'follow_up')
            ->assertJsonPath('answer', "**Kabel LAN**\nHarga: Rp 25.000 / pcs");
    }

    public function test_chatbot_returns_pos_sales_summary_from_sales_query_service(): void
    {
        $user = User::factory()->create();

        ErpChatParserRule::query()->create([
            'name' => 'POS Hari Ini',
            'intent_key' => 'pos_sales_today',
            'keywords' => ['pos', 'hari ini'],
            'match_mode' => 'and',
            'priority' => 1,
            'is_active' => true,
        ]);

        PosSale::query()->create([
            'number' => 'POS-001',
            'sales_channel' => 'retail',
            'gross_total' => 150000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 150000,
            'cash_paid' => 150000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->setTime(10, 0),
        ]);

        PosSale::query()->create([
            'number' => 'POS-OLD',
            'sales_channel' => 'retail',
            'gross_total' => 99000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 99000,
            'cash_paid' => 99000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->subDay()->setTime(10, 0),
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'pos hari ini',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'pos_sales_today')
            ->assertJsonPath('answer', "**POS hari ini**\nTransaksi: 1\nTotal penjualan: Rp 150.000\nRata-rata/trx: Rp 150.000");
    }

    public function test_chatbot_returns_unpaid_invoice_summary_from_invoice_query_service(): void
    {
        $user = User::factory()->create();

        ErpChatParserRule::query()->create([
            'name' => 'Invoice Unpaid',
            'intent_key' => 'invoice_unpaid_list',
            'keywords' => ['invoice', 'belum dibayar'],
            'match_mode' => 'and',
            'priority' => 1,
            'is_active' => true,
        ]);

        $project = Project::query()->create([
            'name' => 'Implementasi CCTV',
            'client_name' => 'PT Mitra',
            'total_value' => 500000,
            'status' => 'berjalan',
            'invoice_number' => 'INV-PRJ-001',
        ]);

        ProjectPayment::query()->create([
            'project_id' => $project->id,
            'term_number' => 1,
            'percentage' => 100,
            'amount' => 500000,
            'document_status' => 'draft',
            'paid_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'invoice belum dibayar',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'invoice_unpaid_list')
            ->assertJsonPath('answer', "**Invoice belum dibayar** (1):\n- Implementasi CCTV | INV-PRJ-001 | Termin 1 | Rp 500.000\n\n**Total: Rp 500.000**");
    }

    public function test_chatbot_uses_built_in_rules_when_parser_rule_table_is_empty(): void
    {
        $user = User::factory()->create();

        PosSale::query()->create([
            'number' => 'POS-001',
            'sales_channel' => 'retail',
            'gross_total' => 175000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 175000,
            'cash_paid' => 175000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->setTime(11, 30),
        ]);

        $this->assertDatabaseCount('erp_chat_parser_rules', 0);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'pos hari ini',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'pos_sales_today')
            ->assertJsonPath('answer', "**POS hari ini**\nTransaksi: 1\nTotal penjualan: Rp 175.000\nRata-rata/trx: Rp 175.000");
    }

    public function test_chatbot_prefers_specific_low_stock_intent_over_generic_stock_lookup(): void
    {
        $user = User::factory()->create();

        MasterProduct::query()->create([
            'sku' => 'SKU-LOW-001',
            'name' => 'Patch Cord',
            'category' => 'Kabel',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 20000,
            'stock' => 1,
            'min_stock' => 5,
            'low_stock_alert_enabled' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'stok rendah',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'low_stock_alert')
            ->assertJsonPath('answer', "**⚠️ Stok rendah** (1 produk):\n- Patch Cord | Stok: 1 / Min: 5 pcs");
    }

    public function test_chatbot_follows_up_from_history_when_context_cache_is_empty(): void
    {
        $user = User::factory()->create();

        $product = MasterProduct::query()->create([
            'sku' => 'FOLLOW-001',
            'name' => 'Follow Up Product',
            'category' => 'Test',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 50000,
            'stock' => 25,
            'min_stock' => 5,
            'low_stock_alert_enabled' => true,
        ]);

        $stockLookupResponse = "**{$product->name}**\nStok: {$product->stock} {$product->uom}\n\n💡 Tanya lagi: \"harganya?\" atau \"detailnya?\"";

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'harganya?',
                'history' => [
                    ['role' => 'assistant', 'text' => $stockLookupResponse],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'follow_up')
            ->assertJsonPath('answer', "**{$product->name}**\nHarga: Rp 50.000 / pcs");
    }

    public function test_chatbot_follows_up_from_history_for_stock_when_cache_empty(): void
    {
        $user = User::factory()->create();

        $product = MasterProduct::query()->create([
            'sku' => 'STOCK-FOLLOW',
            'name' => 'Stock Follow Product',
            'category' => 'Test',
            'uom' => 'box',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 75000,
            'stock' => 3,
            'min_stock' => 10,
            'low_stock_alert_enabled' => true,
        ]);

        $priorResponse = "**{$product->name}**\nHarga: Rp 75.000 / box";

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'stoknya?',
                'history' => [
                    ['role' => 'assistant', 'text' => $priorResponse],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'follow_up')
            ->assertJsonPath('answer', "**{$product->name}**\nStok: 3 box ⚠️ *stok rendah*");
    }

    public function test_chatbot_returns_detail_on_follow_up_from_history(): void
    {
        $user = User::factory()->create();

        $product = MasterProduct::query()->create([
            'sku' => 'DETAIL-FOLLOW',
            'name' => 'Detail Follow Product',
            'category' => 'Test',
            'uom' => 'unit',
            'sales_channel' => 'both',
            'product_type' => MasterProduct::PRODUCT_TYPE_FINISHED_GOODS,
            'status' => 'active',
            'selling_price' => 120000,
            'stock' => 8,
            'min_stock' => 2,
            'low_stock_alert_enabled' => true,
        ]);

        $priorResponse = "**{$product->name}**\nStok: 8 unit\n\n💡 Tanya lagi: \"harganya?\" atau \"detailnya?\"";

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'detailnya',
                'history' => [
                    ['role' => 'assistant', 'text' => $priorResponse],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'follow_up');

        $answer = $response->json('answer');
        $this->assertStringContainsString($product->name, $answer);
        $this->assertStringContainsString('unit', $answer);
        $this->assertStringContainsString('120.000', $answer);
    }

    public function test_chatbot_handles_multi_word_typo_via_fuzzy_matching(): void
    {
        $user = User::factory()->create();

        Project::query()->create([
            'name' => 'Fuzzy Project',
            'client_name' => 'PT Fuzzy',
            'total_value' => 300000,
            'status' => 'berjalan',
            'invoice_number' => 'INV-FZZ-001',
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'invois belum dibayar',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'invoice_unpaid_list');
    }

    public function test_chatbot_responds_with_no_match_for_unrecognized_queries(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'bagaimana cara menambahkan user baru di sistem?',
            ])
            ->assertOk()
            ->assertJsonPath('intent', null);
    }

    public function test_parser_rule_with_invalid_intent_key_gets_emptied(): void
    {
        $rule = ErpChatParserRule::query()->create([
            'name' => 'Invalid Intent Rule',
            'intent_key' => 'nonexistent_handler',
            'keywords' => ['nonexistent'],
            'match_mode' => 'or',
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->assertSame('', $rule->fresh()->intent_key);
    }

    public function test_chatbot_rejects_messages_without_authentication(): void
    {
        $this->postJson(route('erp.chatbot.ask'), [
            'message' => 'halo',
        ])
            ->assertStatus(401);
    }

    public function test_chatbot_handles_empty_message_gracefully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => '',
            ])
            ->assertStatus(422);
    }

    public function test_chatbot_handles_very_long_message(): void
    {
        $user = User::factory()->create();

        $longMessage = str_repeat('a', 1001);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => $longMessage,
            ])
            ->assertStatus(422);
    }

    public function test_chatbot_greeting_responds_with_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'halo',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'greeting');

        $answer = $response->json('answer');
        $this->assertStringContainsString('Test User', $answer);
    }

    public function test_chatbot_suggests_closest_intent_for_near_miss(): void
    {
        $user = User::factory()->create();

        Project::query()->create([
            'name' => 'Suggestion Project',
            'client_name' => 'PT Suggest',
            'total_value' => 200000,
            'status' => 'berjalan',
            'invoice_number' => 'INV-SUG-001',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'casflow', // fuzzy match near "cashflow"
            ])
            ->assertOk()
            ->assertJsonPath('intent', null);

        $answer = $response->json('answer');
        $this->assertStringContainsString('Mungkin yang Anda cari', $answer);
    }

    public function test_chatbot_help_shows_all_features(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'bantuan',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'help');

        $answer = $response->json('answer');
        $this->assertStringContainsString('Produk', $answer);
        $this->assertStringContainsString('Penjualan POS', $answer);
        $this->assertStringContainsString('cashflow', $answer);
    }

    public function test_chatbot_parses_send_invoice_without_confirmation(): void
    {
        $user = User::factory()->create();

        $project = Project::query()->create([
            'name' => 'Send Inv Project',
            'client_name' => 'PT Send',
            'client_contact' => 'client@example.com',
            'total_value' => 400000,
            'status' => 'berjalan',
            'invoice_number' => 'INV-SEND-001',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'kirim invoice INV-SEND-001 ke client@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'send_invoice');

        $answer = $response->json('answer');
        $this->assertStringContainsString('Siap mengirim invoice', $answer);
        $this->assertStringContainsString('INV-SEND-001', $answer);
    }

    public function test_chatbot_returns_no_match_when_invoice_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'kirim invoice INV-NOTFOUND-999 ke client@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'send_invoice');

        $answer = $response->json('answer');
        $this->assertStringContainsString('tidak ditemukan', $answer);
    }

    public function test_chatbot_top_selling_excludes_refunded_transactions(): void
    {
        $user = User::factory()->create();

        ErpChatParserRule::query()->create([
            'name' => 'Top Selling',
            'intent_key' => 'top_selling_products',
            'keywords' => ['produk terlaris'],
            'match_mode' => 'or',
            'priority' => 1,
            'is_active' => true,
        ]);

        $salePaid = PosSale::query()->create([
            'number' => 'TOP-PAID',
            'sales_channel' => 'retail',
            'gross_total' => 500000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 500000,
            'cash_paid' => 500000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->setTime(9, 0),
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $salePaid->id,
            'sku' => 'SKU-A',
            'product_name' => 'Top Product A',
            'qty' => 10,
            'unit_price' => 50000,
            'line_total' => 500000,
        ]);

        $saleRefunded = PosSale::query()->create([
            'number' => 'TOP-REFUND',
            'sales_channel' => 'retail',
            'gross_total' => 999999,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 999999,
            'cash_paid' => 999999,
            'change_amount' => 0,
            'status' => 'refunded',
            'sold_at' => now()->setTime(10, 0),
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $saleRefunded->id,
            'sku' => 'SKU-B',
            'product_name' => 'Refunded Product B',
            'qty' => 100,
            'unit_price' => 9999,
            'line_total' => 999999,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'produk terlaris',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'top_selling_products');

        $answer = $response->json('answer');
        $this->assertStringContainsString('Top Product A', $answer);
        $this->assertStringNotContainsString('Refunded Product B', $answer);
    }

    public function test_chatbot_pos_sales_excludes_refunded_transactions(): void
    {
        $user = User::factory()->create();

        ErpChatParserRule::query()->create([
            'name' => 'POS Today',
            'intent_key' => 'pos_sales_today',
            'keywords' => ['pos', 'hari ini'],
            'match_mode' => 'and',
            'priority' => 1,
            'is_active' => true,
        ]);

        PosSale::query()->create([
            'number' => 'POS-OK',
            'sales_channel' => 'retail',
            'gross_total' => 300000,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 300000,
            'cash_paid' => 300000,
            'change_amount' => 0,
            'status' => 'paid',
            'sold_at' => now()->setTime(11, 0),
        ]);

        PosSale::query()->create([
            'number' => 'POS-REF',
            'sales_channel' => 'retail',
            'gross_total' => 888888,
            'discount_total' => 0,
            'additional_fee' => 0,
            'sales_channel_admin_fee' => 0,
            'grand_total' => 888888,
            'cash_paid' => 888888,
            'change_amount' => 0,
            'status' => 'refunded',
            'sold_at' => now()->setTime(12, 0),
        ]);

        $this->actingAs($user)
            ->postJson(route('erp.chatbot.ask'), [
                'message' => 'pos hari ini',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'pos_sales_today')
            ->assertJsonPath('answer', "**POS hari ini**\nTransaksi: 1\nTotal penjualan: Rp 300.000\nRata-rata/trx: Rp 300.000");
    }
}
