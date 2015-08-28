<?php

return [
    'eoko' => [
        'odm' => [
            'driver' => [
                'name' => 'Eoko\\ODM\\Driver\\DynamoDB',
                'options' => [
                    'autoload' => [
                        'Eoko\\ODM\\Driver\\DynamoDB' => __DIR__ . '/../src/'
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Eoko\\ODM\\Driver\\DynamoDB' => 'Eoko\\ODM\\Driver\\DynamoDB\\DynamoDBDriverFactory'
        ]
    ]
];