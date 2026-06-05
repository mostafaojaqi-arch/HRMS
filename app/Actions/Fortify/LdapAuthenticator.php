<?php

namespace App\Actions\Fortify;

class LdapAuthenticator
{
    public function attempt(string $login, string $password): bool
    {
        if ($login === '' || $password === '') {
            return false;
        }

        if (! function_exists('ldap_connect') || ! function_exists('ldap_bind')) {
            return false;
        }

        $connection = $this->connect();

        if (! $connection) {
            return false;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if ((int) config('ldap.timeout', 5) > 0) {
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, (int) config('ldap.timeout', 5));
        }

        if (config('ldap.use_tls', false) && ! @ldap_start_tls($connection)) {
            ldap_close($connection);

            return false;
        }

        $bindDn = (string) config('ldap.bind_dn', '');
        $bindPassword = (string) config('ldap.bind_password', '');

        $isBound = $bindDn === ''
            ? @ldap_bind($connection)
            : @ldap_bind($connection, $bindDn, $bindPassword);

        if (! $isBound) {
            ldap_close($connection);

            return false;
        }

        $baseDn = (string) config('ldap.base_dn', '');

        if ($baseDn === '') {
            ldap_close($connection);

            return false;
        }

        $filterTemplate = (string) config('ldap.user_filter', '(&(objectClass=user)(sAMAccountName=%s))');
        $candidateLogins = [$login];

        if (str_contains($login, '@')) {
            $accountName = strstr($login, '@', true);

            if (is_string($accountName) && $accountName !== '' && ! in_array($accountName, $candidateLogins, true)) {
                $candidateLogins[] = $accountName;
            }
        }

        $userDn = null;

        foreach ($candidateLogins as $candidateLogin) {
            $escapedLogin = $this->escapeFilterValue($candidateLogin);
            $filter = str_replace('%s', $escapedLogin, $filterTemplate);
            $search = @ldap_search($connection, $baseDn, $filter, ['dn']);

            if (! $search) {
                continue;
            }

            $entries = ldap_get_entries($connection, $search);

            if (is_array($entries) && ($entries['count'] ?? 0) > 0 && isset($entries[0]['dn'])) {
                $userDn = $entries[0]['dn'];
                break;
            }
        }

        if (! is_string($userDn) || $userDn === '') {
            ldap_close($connection);

            return false;
        }

        $authenticated = @ldap_bind($connection, $userDn, $password);
        ldap_close($connection);

        return $authenticated;
    }

    private function connect()
    {
        $connectionString = (string) config('ldap.connection_string', '');

        if ($connectionString !== '') {
            return @ldap_connect($connectionString);
        }

        $host = (string) config('ldap.host', '');
        $port = (int) config('ldap.port', 389);

        if ($host === '') {
            return false;
        }

        return @ldap_connect($host, $port);
    }

    private function escapeFilterValue(string $value): string
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        $replacePairs = [
            '\\' => '\\5c',
            '*' => '\\2a',
            '(' => '\\28',
            ')' => '\\29',
            "\x00" => '\\00',
        ];

        return strtr($value, $replacePairs);
    }
}
