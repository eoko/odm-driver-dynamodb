<?php
return [
    'odm' => [
        'metadata' => [
            'driver' => 'Eoko\ODM\Annotation',
            'options' => [
                'autoload' => [
                    'Eoko\Metadata\Annotation' => __DIR__ . '/../src/'
                ],
            ],
        ],
    ],
];