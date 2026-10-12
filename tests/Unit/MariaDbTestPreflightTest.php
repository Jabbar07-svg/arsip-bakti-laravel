<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MariaDbTestPreflight;

class MariaDbTestPreflightTest extends TestCase
{
    public function test_valid_test_target_passes_without_opening_a_connection(): void
    {
        MariaDbTestPreflight::validate(
            $this->validEnvironment(),
            dirname(__DIR__, 2),
            ['phpunit', '--configuration', dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );

        $this->assertTrue(true);
    }

    public function test_production_database_is_rejected(): void
    {
        $environment = $this->validEnvironment();
        $environment['MARIADB_TEST_DATABASE'] = 'arsip_bakti';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The MariaDB test database is not allowlisted.');

        MariaDbTestPreflight::validate(
            $environment,
            dirname(__DIR__, 2),
            ['phpunit', '--configuration='.dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );
    }

    public function test_inherited_database_variables_are_rejected(): void
    {
        $environment = $this->validEnvironment();
        $environment['DB_HOST'] = 'unreviewed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inherited DB_* environment variables are not allowed.');

        MariaDbTestPreflight::validate(
            $environment,
            dirname(__DIR__, 2),
            ['phpunit', '-c', dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );
    }

    public function test_dba_approval_is_required(): void
    {
        $environment = $this->validEnvironment();
        unset($environment['MARIADB_TEST_DBA_APPROVED']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DBA isolation approval is required.');

        MariaDbTestPreflight::validate(
            $environment,
            dirname(__DIR__, 2),
            ['phpunit', '--configuration', dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );
    }

    public function test_inherited_db_url_is_rejected(): void
    {
        $environment = $this->validEnvironment();
        $environment['DB_URL'] = 'unreviewed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inherited DB_* environment variables are not allowed.');

        MariaDbTestPreflight::validate(
            $environment,
            dirname(__DIR__, 2),
            ['phpunit', '--configuration', dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );
    }

    public function test_host_with_pdo_dsn_delimiters_is_rejected(): void
    {
        $environment = $this->validEnvironment();
        $environment['MARIADB_TEST_HOST'] = '127.0.0.1;dbname=arsip_bakti';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The MariaDB test host is invalid.');

        MariaDbTestPreflight::validate(
            $environment,
            dirname(__DIR__, 2),
            ['phpunit', '--configuration='.dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
        );
    }

    public function test_bootstrap_override_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The dedicated phpunit.mariadb.xml profile is required.');

        MariaDbTestPreflight::validate(
            $this->validEnvironment(),
            dirname(__DIR__, 2),
            [
                'phpunit',
                '--configuration='.dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'phpunit.mariadb.xml',
                '--bootstrap',
                'unreviewed-bootstrap.php',
            ],
        );
    }

    public function test_cached_laravel_configuration_is_rejected(): void
    {
        $projectRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mariadb-preflight-'.bin2hex(random_bytes(6));
        $cacheDirectory = $projectRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';
        mkdir($cacheDirectory, 0777, true);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'phpunit.mariadb.xml', '<phpunit/>');
        file_put_contents($cacheDirectory.DIRECTORY_SEPARATOR.'config.php', '<?php return [];');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('A cached Laravel configuration prevents source verification.');

            MariaDbTestPreflight::validate(
                $this->validEnvironment(),
                $projectRoot,
                ['phpunit', '--configuration', $projectRoot.DIRECTORY_SEPARATOR.'phpunit.mariadb.xml'],
            );
        } finally {
            unlink($cacheDirectory.DIRECTORY_SEPARATOR.'config.php');
            unlink($projectRoot.DIRECTORY_SEPARATOR.'phpunit.mariadb.xml');
            rmdir($cacheDirectory);
            rmdir(dirname($cacheDirectory));
            rmdir($projectRoot);
        }
    }

    public function test_unrecognized_phpunit_profile_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The dedicated phpunit.mariadb.xml profile is required.');

        MariaDbTestPreflight::validate(
            $this->validEnvironment(),
            dirname(__DIR__, 2),
            ['phpunit'],
        );
    }

    private function validEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'MARIADB_TEST_DBA_APPROVED' => '1',
            'MARIADB_TEST_DATABASE' => 'arsip_bakti_test',
            'MARIADB_TEST_HOST' => '127.0.0.1',
            'MARIADB_TEST_PORT' => '3306',
            'MARIADB_TEST_USERNAME' => 'synthetic-test-user',
            'MARIADB_TEST_PASSWORD' => 'synthetic-test-password',
            'MARIADB_TEST_COLLATION' => 'utf8mb4_unicode_ci',
            'MARIADB_TEST_EXPECTED_VERSION' => '10.4.32',
        ];
    }
}
