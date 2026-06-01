<?php

namespace Tests\Concerns;

use MongoDB\Client;

trait InteractsWithAuthMongoLogs
{
    protected function authLogCollection(): \MongoDB\Collection
    {
        return (new Client((string) config('database.connections.mongodb.uri')))
            ->selectCollection(
                (string) config('database.connections.mongodb.database'),
                (string) config('logging.channels.auth.collection', 'auth_logs'),
            );
    }

    protected function clearAuthMongoLogs(): void
    {
        $this->authLogCollection()->deleteMany([]);
    }

    protected function assertAuthMongoLogExists(array $criteria): void
    {
        $document = $this->authLogCollection()->findOne($criteria);

        $this->assertNotNull($document, 'No se encontro el log esperado en Mongo: '.json_encode($criteria));
    }

    protected function authMongoLogs(): array
    {
        return $this->authLogCollection()->find()->toArray();
    }
}
