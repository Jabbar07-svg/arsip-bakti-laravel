<?php

namespace Tests\Unit;

use App\Exceptions\LdapUnavailableException;
use App\Services\LdapAuthenticator;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LdapAuthenticatorConfigurationTest extends TestCase
{
    public function test_missing_server_configuration_fails_closed_without_logging_secrets(): void
    {
        config([
            'ldap.logging' => true,
            'ldap.host' => '',
            'ldap.username' => 'service-account',
            'ldap.password' => 'secret-sentinel',
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('LDAP authentication operation failed.', ['operation' => 'configuration_invalid']);

        try {
            app(LdapAuthenticator::class)->authenticate('bakrie', 'user-secret-sentinel');
            $this->fail('Expected incomplete LDAP configuration to be rejected.');
        } catch (LdapUnavailableException $exception) {
            $this->assertSame('LDAP authentication is not configured.', $exception->getMessage());
            $this->assertStringNotContainsString('secret-sentinel', $exception->getMessage());
        }
    }

    public function test_plain_ldap_without_starttls_fails_closed_before_connecting(): void
    {
        config([
            'ldap.logging' => true,
            'ldap.host' => 'directory.invalid',
            'ldap.username' => 'service-account',
            'ldap.password' => 'secret-sentinel',
            'ldap.port' => 389,
            'ldap.scheme' => 'ldap',
            'ldap.start_tls' => false,
            'ldap.base_dn' => 'dc=example,dc=invalid',
            'ldap.user_search_base' => 'ou=people,dc=example,dc=invalid',
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('LDAP authentication operation failed.', ['operation' => 'configuration_invalid']);

        try {
            app(LdapAuthenticator::class)->authenticate('bakrie', 'user-secret-sentinel');
            $this->fail('Expected unencrypted LDAP configuration to be rejected.');
        } catch (LdapUnavailableException $exception) {
            $this->assertSame('LDAP authentication is not configured.', $exception->getMessage());
            $this->assertStringNotContainsString('secret-sentinel', $exception->getMessage());
        }
    }
}
