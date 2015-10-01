<?php

namespace Eoko\ODM\Driver\DynamoDB\Test;

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

    public function scopeListNotValid()
    {
        return [
            [
                [
                    'scope' => 'not valid'
                ],
                [
                    'scope_name' => 12
                ],
            ]
        ];
    }

    /**
     * @dataProvider scopeListNotValid
     * @expectedException \Eoko\ODM\Driver\DynamoDB\MissingIdentifierException
     */
    public function testCreateNotValidEntity($data)
    {
        $this->getDriver()->addItem($data, $this->getClassMetadata());
    }

    /**
     * @dataProvider scopeListNotValid
     * @expectedException \Eoko\ODM\Driver\DynamoDB\MissingIdentifierException
     */
    public function testDeleteNotValidEntity($data)
    {
        $this->getDriver()->deleteItem($data, $this->getClassMetadata());
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


        $this->assertTrue($item == $result);
        $this->assertTrue($item == $actual);
    }

    /**
     * @depends testCreateEntity
     */
    public function testSearchEntity()
    {
        $result = $this->getDriver()->findAll($this->getClassMetadata());
        $this->assertInternalType('array', $result);
        $this->assertEquals(4, count($result));

        $criteria = new Criteria();
        $exp = new ExpressionBuilder();

        $criteria->where($exp->eq('is_default', 'true'));
        $result = $this->getDriver()->findBy($criteria, $this->getClassMetadata());
        $this->assertEquals(2, count($result));

        foreach ($this->scopeList() as $scope) {
            $criteria = new Criteria();
            $exp = new ExpressionBuilder();

            $criteria->where($exp->eq('scope_name', $scope[0]['scope_name']));
            $result = $this->getDriver()->findBy($criteria, $this->getClassMetadata());
            $this->assertTrue($result[0] == $scope[0]);
            $this->assertEquals(1, count($result));
        }
    }

    /**
     * @depends testSearchEntity
     */
    public function testDeleteWithGlobalIndexEntity()
    {
        $criteria = new Criteria();
        $exp = new ExpressionBuilder();

        $criteria->where($exp->eq('is_default', 'true'));
        $result = $this->getDriver()->findBy($criteria, $this->getClassMetadata());

        $this->assertEquals(2, count($result));

        foreach ($result as $item) {
            $result = $this->getDriver()->deleteItem(['scope_name' => $item['scope_name']], $this->getClassMetadata());
            $this->assertTrue($result);
        }

        $result = $this->getDriver()->findBy($criteria, $this->getClassMetadata());
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
