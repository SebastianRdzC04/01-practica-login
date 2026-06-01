<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithAuthMongoLogs;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAuthMongoLogs;

    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearAuthMongoLogs();
    }
}
