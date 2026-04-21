<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

class SyncRoutePermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Sync permissions from routes';

    public function handle()
    {
        $routes = Route::getRoutes();

        $permissions = collect($routes)
            ->map(fn($route) => $route->defaults['permission'] ?? null)
            ->filter()
            ->unique()
            ->values();

        foreach ($permissions as $key) {

            DB::table('permission_actions')->updateOrInsert(
                ['key' => $key],
                [
                    'label' => $this->generateLabel($key)
                ]
            );
        }

        $this->info('Permissions synced ✅');
    }

    private function generateLabel($key)
    {
        // clients.create → Create
        $parts = explode('.', $key);
        return ucfirst($parts[1] ?? $key);
    }
}
