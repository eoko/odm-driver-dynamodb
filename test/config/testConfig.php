<?php

/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

return [
    // Retrieve the list of modules for this application.
    'modules' => [
        'AwsModule',
        'Eoko\ODM\DocumentManager',
        'Eoko\ODM\Metadata\Annotation',
    ],
    // This should be an array of paths in which modules reside.
    // If a string key is provided, the listener will consider that a module
    // namespace, the value of that key the specific path to that module's
    // Module class.
    'module_listener_options' => [
        'module_paths' => [
            './module',
            './vendor',
        ],
        // Using __DIR__ to ensure cross-platform compatibility. Some platforms --
        // e.g., IBM i -- have problems with globs that are not qualified.
        'config_glob_paths' => [realpath(__DIR__).'/autoload/{,*.}{global,local}.php'],
        'config_cache_enabled' => false,
        'module_map_cache_enabled' => false,
    ],
];
