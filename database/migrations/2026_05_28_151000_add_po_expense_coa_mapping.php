<?php

use App\ERP\Accounting\Models\Account;
use App\Models\CategoryCoaMapping;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $exists = CategoryCoaMapping::query()
            ->where('domain', 'purchase_order')
            ->where('category', 'expense')
            ->exists();

        if ($exists) {
            return;
        }

        $expenseAccount = Account::query()->where('code', '5001')->first();

        if ($expenseAccount) {
            CategoryCoaMapping::query()->create([
                'domain' => 'purchase_order',
                'category' => 'expense',
                'account_id' => $expenseAccount->id,
            ]);
        }
    }

    public function down(): void
    {
        CategoryCoaMapping::query()
            ->where('domain', 'purchase_order')
            ->where('category', 'expense')
            ->delete();
    }
};
