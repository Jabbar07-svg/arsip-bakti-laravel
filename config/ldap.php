<?php

return [
    'auth_mode' => env('AUTH_MODE', 'local'),
    'logging' => (bool) env('LDAP_LOGGING', false),
    'default_role' => env('LDAP_DEFAULT_ROLE', 'Pegawai'),
    'host' => env('LDAP_HOST', ''),
    'username' => env('LDAP_USERNAME', ''),
    'password' => env('LDAP_PASSWORD', ''),
    'port' => (int) env('LDAP_PORT', 0),
    'scheme' => env('LDAP_SCHEME', ''),
    'start_tls' => (bool) env('LDAP_START_TLS', false),
    'base_dn' => env('LDAP_BASE_DN', ''),
    'user_search_base' => env('LDAP_USER_SEARCH_BASE', ''),
    'user_search_filter' => env('LDAP_USER_SEARCH_FILTER', '(uid={{username}})'),
    'user_id_attribute' => env('LDAP_USER_ID_ATTRIBUTE', 'uid'),
    'user_mail_attribute' => env('LDAP_USER_MAIL_ATTRIBUTE', 'mail'),
    'user_name_attribute' => env('LDAP_USER_NAME_ATTRIBUTE', 'cn'),
    'timeout' => (int) env('LDAP_TIMEOUT', 5000),
];
