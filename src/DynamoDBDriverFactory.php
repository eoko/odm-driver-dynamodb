<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Sdk;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Eoko\ODM\DocumentManager\Driver\DriverInterface;
use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Aws\Sdk as Aws;

class DynamoDBDriverFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config') ;
        $options = $config['eoko']['odm']['driver']['options'];

        $aws = $serviceLocator->get(Aws::class);
        $client = $aws->createDynamoDb();

        $driver = new DynamoDBDriver($options);
        $driver->setClient($client);
        return $driver;
    }

}