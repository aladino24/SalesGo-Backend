<?php

namespace App\Console\Commands;

use App\Models\SalesLocationPing;
use Illuminate\Console\Command;

class PruneSalesLocationPings extends Command
{
    protected $signature = 'salesgo:prune-location-pings {--days=}';

    protected $description = 'Deletes sales location pings older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('salesgo.location_retention_days'));
        if ($days < 1) {
            $this->error('Retention days must be at least one.');

            return self::FAILURE;
        }
        $deleted = SalesLocationPing::where('recorded_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} location pings older than {$days} days.");

        return self::SUCCESS;
    }
}
