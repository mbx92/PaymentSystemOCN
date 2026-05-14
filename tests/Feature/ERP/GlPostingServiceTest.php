<?php

namespace Tests\Feature\ERP;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_balanced_journal_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $cash = Account::query()->create([
            'code' => 'TST-1001',
            'name' => 'Kas',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);

        $revenue = Account::query()->create([
            'code' => 'TST-4001',
            'name' => 'Pendapatan Jasa',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);

        $service = app(GlPostingService::class);
        $companyId = (int) Company::query()->value('id');
        $entry = $service->post($companyId, 'test', 'TXN-001', 'Testing entry', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
        ]);

        $this->assertEquals('posted', $entry->status->value);
        $this->assertCount(2, $entry->lines);
        $this->assertEquals(100000, (float) $entry->lines->sum('debit'));
        $this->assertEquals(100000, (float) $entry->lines->sum('credit'));
        $this->assertSame($companyId, (int) $entry->company_id);
    }
}
