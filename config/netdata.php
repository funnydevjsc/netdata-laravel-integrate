<?php

return [
    'server' => env('NETDATA_SERVER', 'http://127.0.0.1:19999'),
    'version' => env('NETDATA_VERSION', 2),
    'scope_node' => env('NETDATA_SCOPE_NODE'),
    'username' => env('NETDATA_USERNAME'),
    'password' => env('NETDATA_PASSWORD'),
];
