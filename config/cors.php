<?php
return [
 'paths' => ['api/*'],
 'allowed_methods' => ['GET','POST','OPTIONS'],
 'allowed_origins' => array_values(array_filter(explode(',',env('FRONTEND_ORIGIN','http://localhost:8000')))),
 'allowed_origins_patterns' => [],
 'allowed_headers' => ['Accept','Content-Type'],
 'exposed_headers' => [],
 'max_age' => 600,
 'supports_credentials' => false,
];
