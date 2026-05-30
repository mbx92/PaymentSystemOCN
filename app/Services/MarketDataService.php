<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private const YAHOO_BASE = 'https://query1.finance.yahoo.com/v8/finance/chart';

    public function fetchStockPrice(string $ticker): ?array
    {
        $symbol = strtoupper($ticker);
        if (! str_contains($symbol, '.')) {
            $symbol .= '.JK';
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ])
                ->get(self::YAHOO_BASE.'/'.$symbol, [
                    'interval' => '1d',
                    'range' => '1d',
                ]);

            if (! $response->successful()) {
                Log::warning('Yahoo Finance API returned error', [
                    'ticker' => $ticker,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $result = $data['chart']['result'][0] ?? null;

            if (! $result) {
                return null;
            }

            $meta = $result['meta'];
            $quote = $result['indicators']['quote'][0] ?? [];
            $opens = $quote['open'] ?? [];
            $current = null;

            foreach ($opens as $i => $open) {
                $close = ($quote['close'][$i] ?? null);
                if ($close !== null) {
                    $current = $close;
                }
            }

            if ($current === null) {
                $current = $meta['regularMarketPrice'] ?? null;
            }

            $previousClose = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? null;
            $change = $current !== null && $previousClose !== null
                ? round($current - $previousClose, 2)
                : null;
            $changePercent = $current !== null && $previousClose !== null && $previousClose > 0
                ? round((($current - $previousClose) / $previousClose) * 100, 4)
                : null;

            return [
                'current_price' => $current !== null ? round($current, 2) : null,
                'previous_close' => $previousClose !== null ? round($previousClose, 2) : null,
                'price_change' => $change,
                'price_change_percent' => $changePercent,
            ];
        } catch (ConnectionException $e) {
            Log::warning('Yahoo Finance connection timeout', [
                'ticker' => $ticker,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetchMultiplePrices(array $tickers): array
    {
        $results = [];
        foreach ($tickers as $ticker) {
            $price = $this->fetchStockPrice($ticker);
            if ($price !== null) {
                $results[$ticker] = $price;
            }
        }

        return $results;
    }
}
