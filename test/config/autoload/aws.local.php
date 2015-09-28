<?php

return [

    'eoko' => [
        'odm' => [
            'driver' => [
                'name' => 'Eoko\\ODM\\Driver\\DynamoDB',
                'logger' => 'Log\App',
                'options' => [
                    'prefix' => getenv('TRAVIS_JOB_ID') . '_test_',
                ],
            ],
        ],
    ],

    'aws' => [
        'version' => 'latest',
        'region' => 'eu-west-1',
        'credentials' => [
            'key' => getenv('AWS_DYNAMODB_KEY'),
            'secret' => getenv('AWS_DYNAMODB_SECRET'),
        ],
        'http' => [
            'connect_timeout' => 1,
        ],
    ],
];
