<?php

namespace App\Console\Commands;

use App\Models\PersonalInvestment;
use App\Services\MarketDataService;
use Illuminate\Console\Command;

class FetchStockPrices extends Command
{
    protected $signature = 'investments:fetch-prices
        {--ticker= : Only fetch price for a specific ticker}
        {--investment= : Only fetch price for a specific investment ID}';

    protected $description = 'Fetch current stock prices from Yahoo Finance for all saham investments';

    public function handle(MarketDataService $marketData): int
    {
        $query = PersonalInvestment::query()->where('asset_type', 'saham');

        if ($ticker = $this->option('ticker')) {
            $query->where('ticker', strtoupper($ticker));
        }

        if ($investmentId = $this->option('investment')) {
            $query->where('id', (int) $investmentId);
        }

        $investments = $query->whereNotNull('ticker')->get();

        if ($investments->isEmpty()) {
            $this->warn('No saham investments with ticker found.');

            return Command::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($investments as $investment) {
            $this->line("Fetching {$investment->ticker}...");

            $priceData = $marketData->fetchStockPrice($investment->ticker);

            if ($priceData === null) {
                $this->error("Failed to fetch {$investment->ticker}");
                $failed++;

                continue;
            }

            $investment->update([
                'current_price' => $priceData['current_price'],
                'previous_close' => $priceData['previous_close'],
                'price_change' => $priceData['price_change'],
                'price_change_percent' => $priceData['price_change_percent'],
                'last_synced_at' => now(),
            ]);

            $price = $priceData['current_price'] !== null
                ? 'Rp '.number_format($priceData['current_price'], 0, ',', '.')
                : 'N/A';

            $this->info("{$investment->ticker}: {$price}");
            $updated++;
        }

        $this->info("Done. {$updated} updated, {$failed} failed.");

        return Command::SUCCESS;
    }
}
