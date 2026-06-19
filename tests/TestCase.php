<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithAuthMongoLogs;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAuthMongoLogs;

    use CreatesApplication;

    /**
     * Configuración inicial ejecutada antes de cada prueba.
     *
     * Llama al setUp() padre de Laravel y luego limpia los logs de
     * autenticación en MariaDB para asegurar un estado limpio y
     * predecible en cada test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearAuthMongoLogs();
    }
}
