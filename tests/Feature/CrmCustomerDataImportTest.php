<?php

namespace Tests\Feature;

use App\ERP\CRM\Models\CrmCustomer;
use App\Http\Middleware\ErpMaintenanceMode;
use App\Http\Middleware\LogErpActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class CrmCustomerDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_and_updates_customers_from_csv(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $pic = User::factory()->create([
            'name' => 'PIC CRM',
            'email' => 'pic@example.test',
        ]);

        $existing = CrmCustomer::query()->create([
            'code' => 'CUST-0101',
            'name' => 'Nama Lama',
            'company' => 'PT Lama',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $file = $this->fakeCsvFile(implode("\n", [
            'code,name,company,email,phone,address,business_type,tax_id,source,pic_email,pic_name,is_active,notes',
            'CUST-0101,Nama Baru,PT Baru,billing@baru.test,08123,"Jl. Baru",retail,NPWP-1,import_excel,pic@example.test,,1,Customer lama diperbarui',
            ',Andi Customer,PT Andi,andi@test.local,08999,"Jl. Andi",kontraktor,NPWP-2,referral,,PIC CRM,0,Customer baru dari CSV',
        ]));

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.customers.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('erp.admin.data-import', ['tab' => 'customers']));

        $this->assertDatabaseHas('crm_customers', [
            'id' => $existing->id,
            'code' => 'CUST-0101',
            'name' => 'Nama Baru',
            'company' => 'PT Baru',
            'email' => 'billing@baru.test',
            'pic_user_id' => $pic->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('crm_customers', [
            'name' => 'Andi Customer',
            'company' => 'PT Andi',
            'email' => 'andi@test.local',
            'pic_user_id' => $pic->id,
            'is_active' => false,
            'source' => 'referral',
        ]);

        $this->assertSame(2, CrmCustomer::query()->count());
        $this->assertSame('customers', session('flash.import_kind'));
        $this->assertSame(2, session('flash.imported_count'));
    }

    public function test_skips_row_when_pic_reference_is_unknown(): void
    {
        $this->disableErpMiddleware();

        $user = User::factory()->create();
        $file = $this->fakeCsvFile(implode("\n", [
            'code,name,company,email,phone,address,business_type,tax_id,source,pic_email,pic_name,is_active,notes',
            ',Customer Gagal,PT Gagal,gagal@test.local,08111,"Jl. Gagal",retail,NPWP-X,import_excel,missing@example.test,,1,Harus gagal',
        ]));

        $this->actingAs($user)
            ->post(route('erp.admin.data-import.customers.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('erp.admin.data-import', ['tab' => 'customers']));

        $this->assertDatabaseMissing('crm_customers', [
            'email' => 'gagal@test.local',
        ]);
        $this->assertSame('error', session('flash.type'));
        $this->assertSame('customers', session('flash.import_kind'));
        $this->assertSame(0, session('flash.imported_count'));
    }

    private function fakeCsvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'crm-customers.csv',
            $content
        );
    }

    private function disableErpMiddleware(): void
    {
        $this->withoutMiddleware([
            ErpMaintenanceMode::class,
            LogErpActivity::class,
            RoleMiddleware::class,
        ]);
    }
}
