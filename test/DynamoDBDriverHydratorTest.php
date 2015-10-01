<?php

namespace Eoko\ODM\Driver\DynamoDB\Test;

use Aws\Result;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Eoko\ODM\Driver\DynamoDB\DynamoDBItemHydrator;
use Eoko\ODM\Driver\DynamoDB\DynamoDBValuesHydrator;
use Eoko\ODM\Driver\DynamoDB\Test\Entity\UserEntity;
use Eoko\ODM\Driver\DynamoDB\Transform\ValuesFromDynamoDB;
use Eoko\ODM\Driver\DynamoDB\Transform\ValuesToDynamoDB;

class DynamoDBDriverHydratorTest extends BaseTestCase
{

    public static function getClassMetadata($classname = UserEntity::class)
    {
        return parent::getClassMetadata($classname);
    }

    public function userList()
    {
        return [
            [
                [
                    'username' => 'john'
                ]
            ],
            [
                [
                    'username' => 'pierre',
                    'age' => 22
                ]
            ],
            [
                [
                    'username' => 'marc',
                    'age' => 12.12,
                    'email_verified' => true
                ]
            ],
            [
                [
                    'username' => 'muriel',
                    'email' => 'muriel.vandenheede@eoko.fr',
                    'email_verified' => false,
                    'age' => 12.12,
                ]
            ],
            [
                [
                    'username' => 'muriel',
                    'email' => 'muriel.vandenheede2@eoko.fr',
                    'email_verified' => false,
                    'age' => 12.12,
                ]
            ]
        ];
    }

    /**
     * @dataProvider userList
     */
    public function testHydrator($values)
    {
        $hydrator = new ValuesToDynamoDB();
        $item = $hydrator->transform($values, $this->getClassMetadata()->getFields());

        $hydrator = new ValuesFromDynamoDB();
        $result = $hydrator->transform($item, $this->getClassMetadata()->getFields());

        foreach($values as $key => $value) {
            $this->assertEquals($value, $result[$key]);
        }
    }

}
