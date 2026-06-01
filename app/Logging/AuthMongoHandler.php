<?php

namespace App\Logging;

use Monolog\Handler\MongoDBHandler;
use Monolog\Level;
use MongoDB\Client;

class AuthMongoHandler extends MongoDBHandler
{
    public function __construct(
        int|string|Level $level = 'debug',
        bool $bubble = true,
        ?string $uri = null,
        ?string $database = null,
        ?string $collection = null,
        array $options = [],
        array $driverOptions = [],
    ) {
        $client = new Client(
            $uri ?? (string) config('database.connections.mongodb.uri'),
            $options,
            $driverOptions,
        );

        parent::__construct(
            $client,
            $database ?? (string) config('database.connections.mongodb.database'),
            $collection ?? (string) config('logging.channels.auth.collection', 'auth_logs'),
            $level,
            $bubble,
        );
    }
}
