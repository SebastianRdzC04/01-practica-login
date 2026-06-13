<?php

namespace App\Logging;

use Monolog\Handler\MongoDBHandler;
use Monolog\Level;
use MongoDB\Client;

/**
 * Handler de Monolog para almacenar logs de autenticación en MongoDB.
 *
 * Extiende MongoDBHandler de Monolog y configura automáticamente
 * la conexión a MongoDB usando los valores de configuración de la
 * conexión 'mongodb' de Laravel y el canal 'auth' de logging.
 *
 * @see https://docs.phpdoc.org/ PHPDoc standard
 */
class AuthMongoHandler extends MongoDBHandler
{
    /**
     * Construye el handler y configura la conexión a MongoDB.
     *
     * Crea una instancia del cliente de MongoDB con los parámetros
     * recibidos o los valores por defecto de la configuración de
     * Laravel (database.connections.mongodb), y delega en el
     * constructor padre de Monolog.
     *
     * @param  int|string|\Monolog\Level  $level          Nivel mínimo de log (por defecto 'debug').
     * @param  bool                        $bubble         Si los registros deben propagarse a otros handlers.
     * @param  string|null                 $uri            URI de conexión a MongoDB (opcional).
     * @param  string|null                 $database       Nombre de la base de datos (opcional).
     * @param  string|null                 $collection     Nombre de la colección (opcional).
     * @param  array<string, mixed>        $options        Opciones del cliente MongoDB.
     * @param  array<string, mixed>        $driverOptions  Opciones del driver MongoDB.
     *
     * @see https://docs.phpdoc.org/ PHPDoc standard
     */
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
