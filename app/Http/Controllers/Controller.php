<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    protected const ALLOWED_PER_PAGE = [10, 15, 25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

    protected const MAX_DEADLOCK_RETRIES = 3;

    /**
     * Run a DB transaction with automatic retry on deadlock.
     */
    protected function dbTransaction(callable $callback, ?int $maxRetries = null): mixed
    {
        $maxRetries ??= self::MAX_DEADLOCK_RETRIES;
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $e) {
                $attempts++;
                if ($attempts >= $maxRetries || ! $this->isDeadlock($e)) {
                    throw $e;
                }
                usleep(100000 * $attempts);
            }
        }
    }

    private function isDeadlock(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Deadlock found when trying to get lock')
            || str_contains($message, 'deadlock detected')
            || str_contains($message, 'Lock wait timeout exceeded');
    }

    protected function resolvedPerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);

        return in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : 25;
    }

    /**
     * @param  list<string>  $only
     * @return array<string, mixed>
     */
    protected function filtersWithPerPage(Request $request, array $only): array
    {
        return array_merge($request->only($only), ['per_page' => $this->resolvedPerPage($request)]);
    }
}
