<?php

namespace Tests\Concerns;

use MongoDB\Client;

trait InteractsWithAuthMongoLogs
{
    /**
     * Obtiene la colección de MongoDB donde se almacenan los logs de autenticación.
     *
     * Crea una conexión a MongoDB utilizando la URI y base de datos configuradas
     * en config('database.connections.mongodb'), y selecciona la colección
     * definida en config('logging.channels.auth.collection') o 'auth_logs' por defecto.
     *
     * @return \MongoDB\Collection Colección de MongoDB para logs de autenticación.
     */
    protected function authLogCollection(): \MongoDB\Collection
    {
        return (new Client((string) config('database.connections.mongodb.uri')))
            ->selectCollection(
                (string) config('database.connections.mongodb.database'),
                (string) config('logging.channels.auth.collection', 'auth_logs'),
            );
    }

    /**
     * Elimina todos los documentos de la colección de logs de autenticación.
     *
     * Utiliza deleteMany([]) para limpiar por completo la colección,
     * garantizando un estado inicial predecible antes de cada prueba.
     */
    protected function clearAuthMongoLogs(): void
    {
        $this->authLogCollection()->deleteMany([]);
    }

    /**
     * Verifica que exista al menos un documento en la colección de logs
     * de autenticación que coincida con los criterios proporcionados.
     *
     * Busca un documento en MongoDB usando los criterios dados y falla
     * el test si no se encuentra ninguno, mostrando un mensaje descriptivo
     * con los criterios en JSON.
     *
     * @param  array  $criteria  Criterios de búsqueda (filtro MongoDB).
     */
    protected function assertAuthMongoLogExists(array $criteria): void
    {
        $document = $this->authLogCollection()->findOne($criteria);

        $this->assertNotNull($document, 'No se encontro el log esperado en Mongo: '.json_encode($criteria));
    }

    /**
     * Obtiene todos los documentos de la colección de logs de autenticación.
     *
     * Recupera y retorna el contenido completo de la colección como un array,
     * útil para inspecciones más detalladas en las pruebas.
     *
     * @return array Arreglo de documentos de MongoDB.
     */
    protected function authMongoLogs(): array
    {
        return $this->authLogCollection()->find()->toArray();
    }
}
