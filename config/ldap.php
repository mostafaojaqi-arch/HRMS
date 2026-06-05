<?php

return [
    'enabled' => env('LDAP_ENABLED', false),

    'connection_string' => env('LDAP_CONNECTION_STRING', ''),
    'host' => env('LDAP_HOST', '127.0.0.1'),
    'port' => (int) env('LDAP_PORT', 389),

    'base_dn' => env('LDAP_BASE_DN', ''),
    'bind_dn' => env('LDAP_BIND_DN', ''),
    'bind_password' => env('LDAP_BIND_PASSWORD', ''),

    'user_filter' => env('LDAP_USER_FILTER', '(&(objectClass=user)(sAMAccountName=%s))'),

    'use_tls' => env('LDAP_USE_TLS', false),
    'timeout' => (int) env('LDAP_TIMEOUT', 5),

    'auto_create_local_users' => env('LDAP_AUTO_CREATE_LOCAL_USERS', true),
    'default_role' => env('LDAP_DEFAULT_ROLE', ''),
    'fallback_to_local' => env('LDAP_FALLBACK_TO_LOCAL', true),
];
