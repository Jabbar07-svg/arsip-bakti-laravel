<?php

namespace Tests\Support;

use RuntimeException;

final class MariaDbTestPreflight
{
    private const DATABASE = 'arsip_bakti_test';

    private const REQUIRED_TEST_VARIABLES = [
        'MARIADB_TEST_DBA_APPROVED',
        'MARIADB_TEST_DATABASE',
        'MARIADB_TEST_HOST',
        'MARIADB_TEST_PORT',
        'MARIADB_TEST_USERNAME',
        'MARIADB_TEST_PASSWORD',
        'MARIADB_TEST_COLLATION',
        'MARIADB_TEST_EXPECTED_VERSION',
    ];

    private const OPTIONAL_TEST_VARIABLES = [
        'MARIADB_TEST_SSL_CA',
    ];

    public static function validate(array $environment, string $projectRoot, array $arguments): void
    {
        $failures = [];

        if (($environment['APP_ENV'] ?? 'testing') !== 'testing') {
            $failures[] = 'APP_ENV must be testing.';
        }

        foreach (array_keys($environment) as $name) {
            if (str_starts_with($name, 'DB_')) {
                $failures[] = 'Inherited DB_* environment variables are not allowed.';
                break;
            }
        }

        foreach (array_keys($environment) as $name) {
            if (
                str_starts_with($name, 'MARIADB_TEST_')
                && ! in_array($name, [...self::REQUIRED_TEST_VARIABLES, ...self::OPTIONAL_TEST_VARIABLES], true)
            ) {
                $failures[] = 'An unreviewed MARIADB_TEST_* variable is present.';
                break;
            }
        }

        foreach (self::REQUIRED_TEST_VARIABLES as $name) {
            if (! isset($environment[$name]) || trim((string) $environment[$name]) === '') {
                $failures[] = 'A required MariaDB test environment variable is missing.';
                break;
            }
        }

        if (($environment['MARIADB_TEST_DBA_APPROVED'] ?? null) !== '1') {
            $failures[] = 'DBA isolation approval is required.';
        }

        if (($environment['MARIADB_TEST_DATABASE'] ?? null) !== self::DATABASE) {
            $failures[] = 'The MariaDB test database is not allowlisted.';
        }

        if (($environment['MARIADB_TEST_DATABASE'] ?? null) === 'arsip_bakti') {
            $failures[] = 'The production database is prohibited.';
        }

        $port = $environment['MARIADB_TEST_PORT'] ?? null;
        if (! is_string($port) || ! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            $failures[] = 'The MariaDB test port is invalid.';
        }

        $host = $environment['MARIADB_TEST_HOST'] ?? null;
        if (! is_string($host) || ! self::isSafeHost($host)) {
            $failures[] = 'The MariaDB test host is invalid.';
        }

        $expectedVersion = $environment['MARIADB_TEST_EXPECTED_VERSION'] ?? '';
        if (! is_string($expectedVersion) || ! preg_match('/^\d+(?:\.\d+){1,2}$/', $expectedVersion)) {
            $failures[] = 'The DBA-approved MariaDB version prefix is invalid.';
        }

        if (is_file($projectRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php')) {
            $failures[] = 'A cached Laravel configuration prevents source verification.';
        }

        if (! self::usesDedicatedPhpUnitConfiguration($arguments, $projectRoot)) {
            $failures[] = 'The dedicated phpunit.mariadb.xml profile is required.';
        }

        if ($failures !== []) {
            throw new RuntimeException('MariaDB test preflight blocked: '.implode(' ', array_unique($failures)));
        }
    }

    public static function prepare(array $environment, string $projectRoot, array $arguments): void
    {
        self::validate($environment, $projectRoot, $arguments);

        self::setProcessEnvironment('APP_ENV', 'testing');
        self::setProcessEnvironment('DB_CONNECTION', 'mariadb_test');
        self::setProcessEnvironment('DB_URL', '');
        self::setProcessEnvironment('MARIADB_TEST_PROFILE', '1');
    }

    private static function usesDedicatedPhpUnitConfiguration(array $arguments, string $projectRoot): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === '--bootstrap' || str_starts_with($argument, '--bootstrap=')) {
                return false;
            }
        }

        $configuration = null;

        foreach ($arguments as $index => $argument) {
            if (($argument === '--configuration' || $argument === '-c') && isset($arguments[$index + 1])) {
                $configuration = $arguments[$index + 1];
                break;
            }

            if (str_starts_with($argument, '--configuration=')) {
                $configuration = substr($argument, strlen('--configuration='));
                break;
            }
        }

        if (! is_string($configuration)) {
            return false;
        }

        $expected = realpath($projectRoot.DIRECTORY_SEPARATOR.'phpunit.mariadb.xml');
        $actual = realpath($configuration);

        return $expected !== false && $actual !== false && $actual === $expected;
    }

    private static function isSafeHost(string $host): bool
    {
        if ($host === '' || trim($host) !== $host) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match(
            '/\A(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*\z/',
            $host,
        ) === 1;
    }

    private static function setProcessEnvironment(string $name, string $value): void
    {
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
