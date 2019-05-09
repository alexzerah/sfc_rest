<?php

require __DIR__.'/vendor/autoload.php';

use Guzzle\Http\Client;

// create our http client (Guzzle)
$client = new Client('http://localhost:8000', array(
    'request.options' => array(
        'exceptions' => false,
    )
));

$nickname = 'ObjectOrienter'.rand(0,999);
$data = [
    'nickname' => $nickname,
    'avatarNumber' => 5,
    'tagLine' => 'A test dev!'
];

$request = $client->post('/api/programmers', null, json_encode($data));
$response = $request->send();

echo $response;
echo "\n\n";

$url = $response->getHeader('Location');
$request = $client->get($url);
$response = $request->send();

$request = $client->get('/api/programmers');
$response = $request->send();

echo $response;
echo "\n\n";
