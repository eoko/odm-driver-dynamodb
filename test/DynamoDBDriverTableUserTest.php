<?php

namespace Eoko\ODM\Driver\DynamoDB\Test;

use Aws\Result;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Eoko\ODM\Driver\DynamoDB\Test\Entity\UserEntity;

class DynamoDBDriverTableUserTest extends BaseTestCase
{
    protected $johnEntity;

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
    public function testCreateEntity($data)
    {
        $user = new UserEntity();
        $user->exchangeArray($data);
        $item = $user->getArrayCopy();

        $result = $this->getDriver()->addItem($item, $this->getClassMetadata());
        $actual = $this->getDriver()->getItem(['username' => $user->getUsername()], $this->getClassMetadata());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($item == $actual);
    }

    /**
     * @depends testCreateEntity
     */
    public function testSearchEntity()
    {
        $entity = new UserEntity();
        $entity->setUsername('john');

        $result = $this->getDriver()->findAll($this->getClassMetadata());

        foreach ($result as $key => $item) {
            unset($result[$key]);
            $result[$item['username']] = $item;
        }

        $users = [];
        foreach ($this->userList() as $list) {
            foreach ($list as $user) {
                $users[$user['username']] = $user;
            }
        }

        foreach ($users as $username => $user) {
            $this->assertTrue($result[$username] == $user);
        }

        $criteria = new Criteria();
        $exp = new ExpressionBuilder();

        foreach ($users as $username => $user) {
            $criteria->where($exp->eq('username', $username));

            $result = $this->getDriver()->findBy($criteria, null, $this->getClassMetadata());

            $this->assertEquals(1, count($result));
            $this->assertTrue($users[$username] == array_pop($result));
        }
    }

    /**
     * @depends testSearchEntity
     */
    public function testDeleteWithBasicIndexEntity()
    {
        $entity = new UserEntity();
        $entity->setUsername('john');
        $this->assertInstanceOf(Result::class, $this->getDriver()->deleteItem($entity->getArrayCopy(), $this->getClassMetadata()));
    }

    /**
     * @depends testCreateEntity
     */
    public function testDeleteWithGlobalIndexEntity()
    {
        $entity = new UserEntity();
        $entity->setUsername('john');
        $entity->setEmailVerified(false);

        $this->getDriver()->addItem($entity->getArrayCopy(), $this->getClassMetadata());
        $this->assertInstanceOf(Result::class, $this->getDriver()->deleteItem($entity->getArrayCopy(), $this->getClassMetadata()));
    }

    public static function setUpBeforeClass()
    {
        $retry = 0;
        // Ensure that the Table is finished to be created

        self::getDriver()->createTable(self::getClassMetadata());

        while (self::getDriver()->getTableStatus(self::getClassMetadata()) !== 'ACTIVE' && $retry < 5) {
            sleep($retry++ * $retry);
        }
    }

    public static function tearDownAfterClass()
    {
        $retry = 0;

        self::getDriver()->deleteTable(self::getClassMetadata());

        // Ensure that the Table is really deleted
        while (!self::getDriver()->isTable(self::getClassMetadata()) && $retry < 5) {
            sleep($retry++ * $retry);
        }
    }
}
