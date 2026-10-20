<?php

namespace App\Services;

use App\Exceptions\LdapUnavailableException;
use Illuminate\Support\Facades\Log;

class LdapAuthenticator
{
    /**
     * @return array{username: string, name: ?string, email: ?string}|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (! extension_loaded('ldap')) {
            $this->logFailure('extension_missing');
            throw new LdapUnavailableException('LDAP authentication is not available.');
        }

        $host = trim((string) config('ldap.host'));
        $port = (int) config('ldap.port');
        $scheme = (string) config('ldap.scheme');
        $serviceUsername = (string) config('ldap.username');
        $servicePassword = (string) config('ldap.password');
        $baseDn = trim((string) config('ldap.base_dn'));
        $searchBase = trim((string) (config('ldap.user_search_base') ?: $baseDn));
        $filterTemplate = (string) config('ldap.user_search_filter');
        $idAttribute = (string) config('ldap.user_id_attribute');
        $nameAttribute = (string) config('ldap.user_name_attribute');
        $mailAttribute = (string) config('ldap.user_mail_attribute');
        $timeoutMilliseconds = (int) config('ldap.timeout');
        $startTls = (bool) config('ldap.start_tls');

        if (
            $host === ''
            || preg_match('/[\s\/\\\\#?]/', $host) === 1
            || ! in_array($scheme, ['ldap', 'ldaps'], true)
            || $port < 1
            || $port > 65535
            || $baseDn === ''
            || $searchBase === ''
            || $serviceUsername === ''
            || $servicePassword === ''
            || $timeoutMilliseconds < 1
            || ($scheme === 'ldaps' && $startTls)
            || ($scheme === 'ldap' && ! $startTls)
            || substr_count($filterTemplate, '{{username}}') !== 1
            || ! $this->validAttribute($idAttribute)
            || ! $this->validAttribute($nameAttribute)
            || ! $this->validAttribute($mailAttribute)
        ) {
            $this->logFailure('configuration_invalid');
            throw new LdapUnavailableException('LDAP authentication is not configured.');
        }

        $uriHost = str_contains($host, ':') && ! str_starts_with($host, '[')
            ? '['.$host.']'
            : $host;
        $connection = $this->callLdap(
            fn () => ldap_connect("{$scheme}://{$uriHost}:{$port}")
        );

        if ($connection === false) {
            $this->logFailure('connect_failed');
            throw new LdapUnavailableException('LDAP server connection failed.');
        }

        $timeoutSeconds = $timeoutMilliseconds / 1000;
        $searchTimeLimit = max(1, (int) ceil($timeoutSeconds));

        try {
            if (
                ! $this->callLdap(fn () => ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3))
                || ! $this->callLdap(fn () => ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $timeoutSeconds))
                || ! $this->callLdap(fn () => ldap_set_option($connection, LDAP_OPT_REFERRALS, 0))
            ) {
                $this->logFailure('connection_options_failed');
                throw new LdapUnavailableException('LDAP connection setup failed.');
            }

            if ($startTls && ! $this->callLdap(fn () => ldap_start_tls($connection))) {
                $this->logFailure('start_tls_failed');
                throw new LdapUnavailableException('LDAP secure connection setup failed.');
            }

            if (! $this->callLdap(fn () => ldap_bind($connection, $serviceUsername, $servicePassword))) {
                $this->logFailure('service_bind_failed');
                throw new LdapUnavailableException('LDAP service authentication failed.');
            }

            $filter = str_replace('{{username}}', $this->escapeFilterValue($username), $filterTemplate);
            $attributes = array_values(array_unique([$idAttribute, $nameAttribute, $mailAttribute]));
            $search = $this->callLdap(
                fn () => ldap_search(
                    $connection,
                    $searchBase,
                    $filter,
                    $attributes,
                    0,
                    2,
                    $searchTimeLimit,
                )
            );

            if ($search === false) {
                $this->logFailure('search_failed');
                throw new LdapUnavailableException('LDAP user search failed.');
            }

            $entries = $this->callLdap(fn () => ldap_get_entries($connection, $search));
            if (! is_array($entries)) {
                $this->logFailure('search_result_failed');
                throw new LdapUnavailableException('LDAP user search failed.');
            }

            if ($entries['count'] === 0) {
                return null;
            }

            if ($entries['count'] !== 1 || ! is_string($entries[0]['dn'] ?? null)) {
                $this->logFailure('search_result_ambiguous');
                throw new LdapUnavailableException('LDAP returned an ambiguous user result.');
            }

            $identityUsername = $this->attributeValue($entries[0], $idAttribute);
            if ($identityUsername === null) {
                $this->logFailure('user_identifier_missing');
                throw new LdapUnavailableException('LDAP user identifier is unavailable.');
            }

            if (! $this->callLdap(fn () => ldap_bind($connection, $entries[0]['dn'], $password))) {
                if ($this->callLdap(fn () => ldap_errno($connection)) === LDAP_INVALID_CREDENTIALS) {
                    return null;
                }

                $this->logFailure('user_bind_failed');
                throw new LdapUnavailableException('LDAP user authentication failed.');
            }

            return [
                'username' => $identityUsername,
                'name' => $this->attributeValue($entries[0], $nameAttribute),
                'email' => $this->validEmail($this->attributeValue($entries[0], $mailAttribute)),
            ];
        } finally {
            $this->callLdap(fn () => ldap_unbind($connection));
        }
    }

    private function validAttribute(string $attribute): bool
    {
        return preg_match('/\A(?:[A-Za-z][A-Za-z0-9-]*|(?:[0-9]+\.)+[0-9]+)\z/', $attribute) === 1;
    }

    private function escapeFilterValue(string $value): string
    {
        return strtr($value, [
            '\\' => '\5c',
            '*' => '\2a',
            '(' => '\28',
            ')' => '\29',
            "\0" => '\00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function attributeValue(array $entry, string $attribute): ?string
    {
        $values = $entry[strtolower($attribute)] ?? null;
        if (! is_array($values) || ! is_string($values[0] ?? null)) {
            return null;
        }

        $value = trim($values[0]);

        return $value === '' ? null : $value;
    }

    private function validEmail(?string $email): ?string
    {
        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function callLdap(callable $operation): mixed
    {
        set_error_handler(static fn (int $severity): bool => $severity === E_WARNING, E_WARNING);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private function logFailure(string $operation): void
    {
        if (config('ldap.logging')) {
            Log::warning('LDAP authentication operation failed.', ['operation' => $operation]);
        }
    }
}
