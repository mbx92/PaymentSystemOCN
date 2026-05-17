<?php

namespace Tests\Feature;

use App\Models\PersonalCategory;
use App\Models\PersonalTransaction;
use App\Models\PersonalWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonalFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
    }

    public function test_categories_page_lists_user_categories(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('personal.categories'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personal/Categories')
                ->has('categories', 7));
    }

    public function test_can_create_update_and_delete_category(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->post(route('personal.categories.store'), [
                'name' => 'Tabungan rutin',
                'type' => 'expense',
                'color' => '#22c55e',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Kategori ditambahkan.');

        $category = PersonalCategory::query()
            ->where('user_id', $user->id)
            ->where('name', 'Tabungan rutin')
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('personal.categories.update', $category), [
                'name' => 'Tabungan bulanan',
                'type' => 'expense',
                'color' => '#16a34a',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Kategori diperbarui.');

        $this->assertSame('Tabungan bulanan', $category->fresh()->name);

        $this->actingAs($user)
            ->delete(route('personal.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Kategori dihapus.');

        $this->assertDatabaseMissing('personal_categories', ['id' => $category->id]);
    }

    public function test_wallets_page_and_crud(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('personal.wallets'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personal/Wallets')
                ->has('wallets', 1));

        $default = PersonalWallet::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('personal.wallets.store'), [
                'name' => 'BCA Payroll',
                'currency' => 'IDR',
                'sort_order' => 1,
                'is_default' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Dompet ditambahkan.');

        $bank = PersonalWallet::query()
            ->where('user_id', $user->id)
            ->where('name', 'BCA Payroll')
            ->firstOrFail();

        $this->actingAs($user)
            ->patch(route('personal.wallets.update', $bank), [
                'name' => 'BCA Giro',
                'currency' => 'IDR',
                'sort_order' => 2,
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($bank->fresh()->is_default);
        $this->assertFalse($default->fresh()->is_default);

        PersonalTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $default->id,
            'category_id' => null,
            'type' => 'expense',
            'amount' => '50000.00',
            'occurred_on' => '2026-05-17',
            'note' => null,
        ]);

        $this->actingAs($user)
            ->delete(route('personal.wallets.destroy', $default))
            ->assertRedirect()
            ->assertSessionHas('flash.type', 'error');

        $this->actingAs($user)
            ->delete(route('personal.wallets.destroy', $bank))
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Dompet dihapus.');
    }

    public function test_transaction_requires_wallet(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('personal.transactions'))->assertOk();

        $wallet = PersonalWallet::query()->where('user_id', $user->id)->firstOrFail();
        $category = PersonalCategory::query()
            ->where('user_id', $user->id)
            ->where('type', 'expense')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('personal.transactions.store'), [
                'wallet_id' => $wallet->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 125000,
                'occurred_on' => '2026-05-17',
                'note' => 'Belanja',
            ])
            ->assertRedirect()
            ->assertSessionHas('flash.message', 'Transaksi tersimpan.');

        $this->assertDatabaseHas('personal_transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'category_id' => $category->id,
            'amount' => '125000.00',
        ]);
    }
}
