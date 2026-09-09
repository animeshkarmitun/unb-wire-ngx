<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_pgsql_read_host_is_configured(): void
    {
        $config = config('database.connections.pgsql');

        $this->assertArrayHasKey('read', $config);
        $this->assertArrayHasKey('host', $config['read']);
        $this->assertIsArray($config['read']['host']);
        $this->assertNotEmpty($config['read']['host']);
    }

    public function test_pgsql_write_host_is_configured(): void
    {
        $config = config('database.connections.pgsql');

        $this->assertArrayHasKey('write', $config);
        $this->assertArrayHasKey('host', $config['write']);
        $this->assertIsArray($config['write']['host']);
        $this->assertNotEmpty($config['write']['host']);
    }

    public function test_pgsql_sticky_option_is_enabled(): void
    {
        $this->assertTrue(config('database.connections.pgsql.sticky'));
    }

    public function test_pgsql_read_host_falls_back_to_db_host(): void
    {
        $expected = env('DB_READ_HOST', env('DB_HOST', '127.0.0.1'));

        $this->assertEquals(
            $expected,
            config('database.connections.pgsql.read.host')[0]
        );
    }

    public function test_pgsql_write_host_falls_back_to_db_host(): void
    {
        $expected = env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1'));

        $this->assertEquals(
            $expected,
            config('database.connections.pgsql.write.host')[0]
        );
    }

    public function test_basic_select_query_executes(): void
    {
        $result = \DB::connection('sqlite')->select('SELECT 1 AS value');

        $this->assertEquals(1, $result[0]->value);
    }
}
