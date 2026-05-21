<?php

namespace Tests\Feature;

use App\ERP\Accounting\Models\Payable;
use App\ERP\Inventory\Models\Warehouse;
use App\ERP\Purchasing\Models\PurchaseOrder;
use App\ERP\Purchasing\Models\Vendor;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\MasterProduct;
use App\Models\MasterProductWarehouseStock;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\UserNotificationRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_center_includes_multiple_groups_beyond_low_stock(): void
    {
        $user = User::factory()->create();

        MasterProduct::query()->create([
            'sku' => 'SKU-LOW-001',
            'name' => 'Kabel LAN',
            'category' => 'Kabel',
            'uom' => 'roll',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'selling_price' => 150000,
            'stock' => 2,
            'min_stock' => 5,
            'low_stock_alert_enabled' => true,
        ]);

        $project = Project::query()->create([
            'name' => 'Project CCTV',
            'client_name' => 'PT Klien',
            'total_value' => 5000000,
            'status' => 'berjalan',
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'title' => 'Survey lokasi',
            'status' => 'todo',
            'assigned_user_id' => $user->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $vendor = Vendor::query()->create([
            'code' => 'SUP-001',
            'name' => 'Supplier Utama',
            'is_active' => true,
        ]);

        Payable::query()->create([
            'vendor_id' => $vendor->id,
            'bill_no' => 'BILL-001',
            'bill_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'amount' => 1200000,
            'paid_amount' => 0,
            'status' => DocumentStatus::Approved,
        ]);

        PurchaseOrder::query()->create([
            'number' => 'PO-000001',
            'vendor_id' => $vendor->id,
            'order_date' => now()->subDays(5)->toDateString(),
            'eta_date' => now()->toDateString(),
            'total_amount' => 900000,
            'status' => DocumentStatus::Approved,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('notificationCenter.total_count', 4)
                ->has('notificationCenter.groups', 4)
                ->where('notificationCenter.groups.0.key', 'low_stock')
                ->where('notificationCenter.groups.1.key', 'project_tasks')
                ->where('notificationCenter.groups.2.key', 'supplier_bills')
                ->where('notificationCenter.groups.3.key', 'purchase_orders'));
    }

    public function test_notification_center_includes_reserved_stock_and_mismatch_inventory_groups(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'is_active' => true,
        ]);

        $product = MasterProduct::query()->create([
            'sku' => 'SKU-INV-001',
            'name' => 'Switch 8 Port',
            'category' => 'Network',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'selling_price' => 350000,
            'stock' => 10,
            'min_stock' => 1,
            'low_stock_alert_enabled' => false,
        ]);

        MasterProductWarehouseStock::query()->create([
            'master_product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => 10,
            'reserved_qty' => 3,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('notificationCenter.total_count', 2)
                ->has('notificationCenter.groups', 2)
                ->where('notificationCenter.groups.0.key', 'reserved_stock')
                ->where('notificationCenter.groups.1.key', 'stock_mismatch'));
    }

    public function test_notification_can_be_marked_read_and_unread(): void
    {
        $user = User::factory()->create();

        MasterProduct::query()->create([
            'sku' => 'SKU-LOW-002',
            'name' => 'Patch Cord',
            'category' => 'Kabel',
            'uom' => 'pcs',
            'sales_channel' => 'both',
            'product_type' => 'finished_goods',
            'status' => 'active',
            'selling_price' => 20000,
            'stock' => 1,
            'min_stock' => 5,
            'low_stock_alert_enabled' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.mark-read'), [
                'notification_id' => 'low-stock-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_notification_reads', [
            'user_id' => $user->id,
            'notification_id' => 'low-stock-1',
        ]);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->where('notificationCenter.total_count', 0)
                ->where('notificationCenter.items.0.read', true));

        $this->actingAs($user)
            ->delete(route('notifications.mark-unread'), [
                'notification_id' => 'low-stock-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_notification_reads', [
            'user_id' => $user->id,
            'notification_id' => 'low-stock-1',
        ]);
    }
}
