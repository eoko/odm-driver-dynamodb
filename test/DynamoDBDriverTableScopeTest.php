<?php

namespace Eoko\ODM\Driver\DynamoDB\Test;

use Aws\Result;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Eoko\ODM\Driver\DynamoDB\Test\Entity\ScopeEntity;

class DynamoDBDriverTableScopeTest extends BaseTestCase
{
    protected $johnEntity;

    public static function getClassMetadata($classscope_name = ScopeEntity::class)
    {
        return parent::getClassMetadata($classscope_name);
    }

    public function scopeList()
    {
        return [
            [
                [
                    'scope_name' => 'scope_1'
                ]
            ],
            [
                [
                    'scope_name' => 'scope_2',
                    'is_default' => 'true',
                ]
            ],
            [
                [
                    'scope_name' => 'scope_3',
                    'is_default' => 'false',
                ]
            ],
            [
                [
                    'scope_name' => 'scope_4',
                    'is_default' => 'true',
                ]
            ],
        ];
    }

    /**
     * @dataProvider scopeList
     */
    public function testCreateEntity($data)
    {
        $scope = new ScopeEntity();
        $scope->exchangeArray($data);
        $item = $scope->getArrayCopy();

        $result = $this->getDriver()->addItem($item, $this->getClassMetadata());
        $actual = $this->getDriver()->getItem(['scope_name' => $scope->getScopeName()], $this->getClassMetadata());

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($item == $actual);
    }

    /**
     * @depends testCreateEntity
     */
    public function testSearchEntity()
    {
        $entity = new ScopeEntity();
        $entity->setScopeName('scope_1');

        $result = $this->getDriver()->findAll($this->getClassMetadata());

        foreach ($result as $key => $item) {
            unset($result[$key]);
            $result[$item['scope_name']] = $item;
        }

        $scopes = [];
        foreach ($this->scopeList() as $list) {
            foreach ($list as $scope) {
                $scopes[$scope['scope_name']] = $scope;
            }
        }

        foreach ($scopes as $scope_name => $scope) {
            $this->assertTrue($result[$scope_name] == $scope);
        }

        $criteria = new Criteria();
        $exp = new ExpressionBuilder();

        $criteria->where($exp->eq('is_default', 'true'));

        $result = $this->getDriver()->findBy($criteria, null, $this->getClassMetadata());

        $this->assertEquals(2, count($result));

        while (count($result) > 0) {
            $scope = array_pop($result);
            $this->assertTrue($scopes[$scope['scope_name']] == $scope);
        }
    }

    /**
     * @depends testCreateEntity
     */
    public function testDeleteWithGlobalIndexEntity()
    {
        $criteria = new Criteria();
        $exp = new ExpressionBuilder();

        $criteria->where($exp->eq('is_default', 'true'));
        $result = $this->getDriver()->findBy($criteria, null, $this->getClassMetadata());

        $this->assertEquals(2, count($result));

        foreach ($result as $item) {
            $this->getDriver()->deleteItem(['scope_name' => $item['scope_name']], $this->getClassMetadata());
        }

        $result = $this->getDriver()->findBy($criteria, null, $this->getClassMetadata());
        $this->assertEquals(0, count($result));
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
