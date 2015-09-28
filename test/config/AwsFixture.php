<?php

return [
    'createTable' => [
        'createUserTable' => [
            'request' => [
                'AttributeDefinitions' => [
                    [
                        'AttributeName' => 'username',
                        'AttributeType' => 'S',
                    ],
                    [
                        'AttributeName' => 'email_verified',
                        'AttributeType' => 'BOOL',
                    ],
                    [
                        'AttributeName' => 0,
                        'AttributeType' => 'S',
                    ],
                ],
                'KeySchema' =>
                    [
                        [
                            'AttributeName' => 'username',
                            'KeyType' => 'HASH',
                        ],
                    ],
                'ProvisionedThroughput' =>
                    [
                        'ReadCapacityUnits' => 1,
                        'WriteCapacityUnits' => 1,
                    ],
                'GlobalSecondaryIndexes' =>
                    [
                        [
                            'IndexName' => 'username_email-verified_index',
                            'KeySchema' =>
                                [
                                    [
                                        'AttributeName' => 'username',
                                        'KeyType' => 'HASH',
                                    ],
                                    [
                                        'AttributeName' => 'email_verified',
                                        'KeyType' => 'RANGE',
                                    ],
                                ],
                            'Projection' => 'ALL',
                            'ProvisionedThroughput' =>
                                [
                                    'ReadCapacityUnits' => 1,
                                    'WriteCapacityUnits' => 1,
                                ],
                        ],
                        [
                            'IndexName' => 'username_index',
                            'KeySchema' =>
                                [
                                    [
                                        'AttributeName' => 0,
                                        'KeyType' => 'username',
                                    ],
                                ],
                            'Projection' => 'ALL',
                            'ProvisionedThroughput' =>
                                [
                                    'ReadCapacityUnits' => 1,
                                    'WriteCapacityUnits' => 1,
                                ],
                        ],
                    ],
                'TableName' => 'test_oauth_users',
            ],
            'response' => [
                'data' => [
                    'TableDescription' => [
                        'AttributeDefinitions' => [
                            [
                                'AttributeName' => 'authorization_code',
                                'AttributeType' => 'S',
                            ],
                        ],
                        'TableName' => 'default_oauth_authorization_code',
                        'KeySchema' =>
                            [
                                [
                                    'AttributeName' => 'authorization_code',
                                    'KeyType' => 'HASH',
                                ],
                            ],
                        'TableStatus' => 'CREATING',
                        'CreationDateTime' => new \Aws\Api\DateTimeResult('now'),

                        'ProvisionedThroughput' =>
                            [
                                'NumberOfDecreasesToday' => 0,
                                'ReadCapacityUnits' => 1,
                                'WriteCapacityUnits' => 1,
                            ],
                        'TableSizeBytes' => 0,
                        'ItemCount' => 0,
                        'TableArn' => 'arn:aws:dynamodb:eu-west-1:591955746157:table/default_oauth_authorization_code',
                    ],
                    '@metadata' =>
                        [
                            'statusCode' => 200,
                            'effectiveUri' => 'https://dynamodb.eu-west-1.amazonaws.com',
                            'headers' =>
                                [
                                    'x-amzn-requestid' => 'Q71VJ6FS4GVSL8TLF77KHC6BA7VV4KQNSO5AEMVJF66Q9ASUAAJG',
                                    'x-amz-crc32' => '1621303124',
                                    'content-type' => 'application/x-amz-json-1.0',
                                    'content-length' => '506',
                                    'date' => 'Fri, 25 Sep 2015 12:23:22 GMT',
                                ],
                        ],
                ],
            ]
        ]
    ]
];
