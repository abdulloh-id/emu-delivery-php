<?php

// EMU / MeaSoft Public Sandbox Credentials for Testing
return [
    'emu' => [
        'login'    => $_ENV['EMU_LOGIN'] ?? 'test',
        'password' => $_ENV['EMU_PASSWORD'] ?? 'test123',
        'extra'    => $_ENV['EMU_EXTRA'] ?? 245,
    ],
];
