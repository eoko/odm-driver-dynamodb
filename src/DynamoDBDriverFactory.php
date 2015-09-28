<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\Sdk as Aws;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;

class DynamoDBDriverFactory implements FactoryInterface
{

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $sl
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $sl)
    {
        $config = $sl->get('Config')['eoko']['odm']['driver'];

        $options = isset($config['options']) ? $config['options'] : [];
        $logger = isset($config['logger']) && $sl->has($config['logger']) ? $sl->get($config['logger']) : null;

        $aws = $sl->get(Aws::class);
        $client = $aws->createDynamoDb();

        return new DynamoDBDriver($options, $client, $logger);
    }
}
