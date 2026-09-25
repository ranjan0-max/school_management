<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $config = $application->make('config');

        $connection = $config->get('database.default');
        $database = $config->get('database.connections.sqlite.database');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Unsafe test database configuration. Tests may only run with SQLite :memory:; refusing to touch MySQL.',
            );
        }

        return $application;
    }
}
