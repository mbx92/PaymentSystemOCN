<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Account;
use App\ERP\Inventory\Models\Warehouse;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\MasterProduct;
use App\Models\MasterProductChannelPrice;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class PosSalesChannelPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_catalog_and_checkout_use_selected_sales_channel_price(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);

        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::query()->create([
            'code' => 'cash',
            'name' => 'Cash',
            'status' => 'active',
        ]);
        Warehouse::query()->create([
            'code' => 'WH-POS',
            'name' => 'POS',
            'is_active' => true,
        ]);
        Account::query()->updateOrCreate(['code' => '1001'], ['name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);
        Account::query()->updateOrCreate(['code' => '4002'], ['name' => 'Penjualan POS', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_active' => true]);

        $product = MasterProduct::query()->create([
            'sku' => 'POS-CH-001',
            'name' => 'Produk Channel',
            'category' => 'General',
            'uom' => 'pcs',
            'sales_channel' => 'pos',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'selling_price' => 10000,
            'stock' => 5,
        ]);

        MasterProductChannelPrice::query()->create([
            'master_product_id' => $product->id,
            'sales_channel' => 'grosir',
            'label' => 'Grosir',
            'selling_price' => 8000,
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get(route('erp.sales.pos'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Sales/POS')
                ->where('products.0.channel_prices.retail', 10000)
                ->where('products.0.channel_prices.grosir', 8000));

        $response = $this
            ->actingAs($user)
            ->postJson(route('erp.sales.pos.checkout'), [
                'sales_channel' => 'grosir',
                'payment_method_id' => $paymentMethod->id,
                'cash_paid' => 8000,
                'items' => [[
                    'master_product_id' => $product->id,
                    'sku' => $product->sku,
                    'uom' => 'pcs',
                    'qty' => 1,
                    'unit_price' => 1,
                    'discount_percent' => 0,
                    'multiplier' => 1,
                    'price_operation' => 'multiply',
                ]],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('sales_channel', 'grosir')
            ->assertJsonPath('grand_total', 8000);

        $this->assertDatabaseHas('pos_sales', [
            'sales_channel' => 'grosir',
            'grand_total' => 8000,
        ]);
        $this->assertDatabaseHas('pos_sale_items', [
            'master_product_id' => $product->id,
            'unit_price' => 8000,
            'line_total' => 8000,
        ]);
    }
}
