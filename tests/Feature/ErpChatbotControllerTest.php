<?php

namespace Tests\Feature;

use App\Models\ErpChatParserRule;
use App\Models\MasterProduct;
use App\Models\PosSale;
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
}
