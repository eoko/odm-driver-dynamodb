<?php

namespace Eoko\ODM\Driver\DynamoDB\Test;

use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Eoko\ODM\Driver\DynamoDB\DynamoDBDriver;
use Eoko\ODM\Driver\DynamoDB\DynamoDBDriverFactory;

/**
 * Class BaseTestCase
 * @package Eoko\ODM\Driver\DynamoDB\Test
 */
class BaseTestCase extends \PHPUnit_Framework_TestCase
{

    public static function getClassMetadata($classname)
    {
        return new ClassMetadata($classname, Bootstrap::getServiceManager()->get('Eoko\\ODM\\Metadata\\Annotation'));
    }

    /**
     * @return DynamoDBDriver
     */
    public static function getDriver()
    {
        /** @var  $driver */
        return (new DynamoDBDriverFactory())->createService(Bootstrap::getServiceManager());
    }
}
