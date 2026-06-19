<?php

namespace Tests\Concerns;

use App\Models\AuthLog;
use Illuminate\Support\Facades\DB;

trait InteractsWithAuthMongoLogs
{
    protected function clearAuthMongoLogs(): void
    {
        AuthLog::truncate();
    }

    protected function assertAuthMongoLogExists(array $criteria): void
    {
        $query = AuthLog::query();

        foreach ($criteria as $key => $value) {
            if (str_starts_with($key, 'context.')) {
                $field = substr($key, 8);
                $query->where('context->' . $field, $value);
            } else {
                $query->where($key, $value);
            }
        }

        $exists = $query->exists();

        $this->assertTrue($exists, 'No se encontro el log esperado: '.json_encode($criteria));
    }

    protected function authMongoLogs(): array
    {
        return AuthLog::all()->toArray();
    }
}
