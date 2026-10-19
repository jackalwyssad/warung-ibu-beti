<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'toko'),
    'password' => env('RABBITMQ_PASSWORD', 'rahasia'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => 'warung.sembako.events',
];
