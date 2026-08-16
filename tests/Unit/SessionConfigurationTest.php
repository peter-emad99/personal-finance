<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

class SessionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cookie_default_is_secure_only_in_production(): void
    {
        $previous = $this->captureEnvironment(['APP_ENV', 'SESSION_SECURE_COOKIE']);

        try {
            $this->setEnvironment('APP_ENV', 'production');
            $this->setEnvironment('SESSION_SECURE_COOKIE', null);
            $production = require base_path('config/session.php');

            $this->assertTrue($production['secure']);

            $this->setEnvironment('APP_ENV', 'local');
            $local = require base_path('config/session.php');

            $this->assertFalse($local['secure']);

            $this->setEnvironment('APP_ENV', 'production');
            $this->setEnvironment('SESSION_SECURE_COOKIE', '');
            $productionWithBlankOverride = require base_path('config/session.php');

            $this->assertTrue($productionWithBlankOverride['secure']);
        } finally {
            $this->restoreEnvironment($previous);
        }
    }

    /** @return array<string, array{putenv: string|false, env: mixed, has_env: bool, server: mixed, has_server: bool}> */
    private function captureEnvironment(array $keys): array
    {
        $previous = [];

        foreach ($keys as $key) {
            $previous[$key] = [
                'putenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'has_env' => array_key_exists($key, $_ENV),
                'server' => $_SERVER[$key] ?? null,
                'has_server' => array_key_exists($key, $_SERVER),
            ];
        }

        return $previous;
    }

    private function setEnvironment(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        Env::enablePutenv();
    }

    /** @param array<string, array{putenv: string|false, env: mixed, has_env: bool, server: mixed, has_server: bool}> $previous */
    private function restoreEnvironment(array $previous): void
    {
        foreach ($previous as $key => $state) {
            if ($state['putenv'] === false) {
                putenv($key);
            } else {
                putenv($key.'='.$state['putenv']);
            }

            if ($state['has_env']) {
                $_ENV[$key] = $state['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($state['has_server']) {
                $_SERVER[$key] = $state['server'];
            } else {
                unset($_SERVER[$key]);
            }
        }

        Env::enablePutenv();
    }
}
