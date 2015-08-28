<?php

class DynamoDBDriverTest extends PHPUnit_Framework_TestCase
{
    
    protected function getFieldsMetadata() {
        
        $field1 = Mockery::mock('Eoko\ODM\Metadata\FieldInterface');
        $field1->shouldReceive('getValue')->andReturn('john');
        $field1->shouldReceive('getType')->andReturn('string');
        
        $field2 = Mockery::mock('Eoko\ODM\Metadata\FieldInterface');
        $field2->shouldReceive('getValue')->andReturn(true);
        $field2->shouldReceive('getType')->andReturn('boolean');
        
        $key = Mockery::mock('Eoko\ODM\Metadata\FieldInterface');
        $key->shouldReceive('getValue')->andReturn('dummy 1');
        $key->shouldReceive('getType')->andReturn('string');
        
        $range = Mockery::mock('Eoko\ODM\Metadata\FieldInterface');
        $range->shouldReceive('getValue')->andReturn('dummy 2');
        $range->shouldReceive('getType')->andReturn('string');
        
        return [
            'key' => [
                'Eoko\ODM\Metadata\FieldInterface' => $key
            ],
            'range' => [
                'Eoko\ODM\Metadata\FieldInterface' => $range
            ],
            'field1' => [
                'Eoko\ODM\Metadata\FieldInterface' => $field1
            ],
            'field2' => [
                'Eoko\ODM\Metadata\FieldInterface' => $field2
            ]
        ];



    }

    protected function getClassMetadata()
    {
        $document = Mockery::mock('Eoko\ODM\DocumentManager\Metadata\DocumentInterface');
        $document->shouldReceive('getTable')->andReturn('dummy_table');

        $identifiers = Mockery::mock('Eoko\ODM\DocumentManager\Metadata\IdentifierInterface');
        $identifiers->shouldReceive('getIdentifier')->andReturn(array(
            'key' => 'HASH',
            'range' => 'RANGE',
        ));

        $identifiers = [
            'key' => [
                'type' => 'HASH',
                'name' => 'key'
            ],
            'range' => [
                'type' => 'RANGE',
                'name' => 'range'
            ]
        ];

        $classMetadata = Mockery::mock('Eoko\ODM\DocumentManager\Metadata\ClassMetadata');
        $classMetadata->shouldReceive('getIdentifier')->andReturn($identifiers);
        $classMetadata->shouldReceive('getDocument')->andReturn($document);
        $classMetadata->shouldReceive('getFields')->andReturn($this->getFieldsMetadata());

        return $classMetadata;
    }

    protected function getClient()
    {
        $client = Mockery::mock('\Aws\DynamoDb\DynamoDbClient');

        $result = new Aws\Result([
            'Item' => [
                'field1' => [ 'S' => 'john'],
                'field2' => [ 'BOOL' => true],
                'key' => [ 'S' => 'dummy 1'],
                'range' => [ 'S' => 'dummy 1'],
            ]
        ]);

        $client->shouldReceive('getItem')->andReturn($result);
        $client->shouldReceive('putItem')->andReturn([]);
        return $client;
    }

    /**
     * @return \Eoko\ODM\DocumentManager\Driver\DriverInterface
     */
    protected function getDriver()
    {
        return new Eoko\ODM\Driver\DynamoDB\DynamoDBDriver($this->getClient());
    }

    /**
     * Call protected/private method of driver.
     *
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     * @return mixed Method return.
     * @internal param object $object Instantiated object that we will run method on.
     */
    protected function invokeMethod($methodName, array $parameters = array())
    {
        $reflection = new ReflectionClass(get_class($this->getDriver()));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->getDriver(), $parameters);
    }

    public function testGetKeyValue()
    {
        $values = ['key' => 'dummy 1', 'range' => 'dummy 2'];
        $result = $this->invokeMethod('getKeyValues', array($values, $this->getClassMetadata()));
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('range', $result);
        $this->assertArraySubset(['S' => 'dummy 1'], $result['key']);
        $this->assertArraySubset(['S' => 'dummy 2'], $result['range']);
    }

    /**
     * @expectedException \Eoko\ODM\Driver\DynamoDB\MissingIdentifierException
     */
    public function testGetKeyValueException()
    {
        $this->invokeMethod('getKeyValues', array(['key' => 'dummy 1'], $this->getClassMetadata()));
        $this->invokeMethod('getKeyValues', array(['range' => 'dummy 1'], $this->getClassMetadata()));
    }

    public function testMapTypeField()
    {
        $this->assertEquals('S', $this->invokeMethod('mapTypeField', ['string']));
        $this->assertEquals('BOOL', $this->invokeMethod('mapTypeField', ['boolean']));
        $this->assertEquals('S', $this->invokeMethod('mapTypeField', ['dummy']));
    }

    public function testGetItemValues()
    {
        $values = [
            'key' => 'dummy 1',
            'range' => 'dummy 2'
        ];

        $result = $this->getDriver()->getItem($values, $this->getClassMetadata());
        $this->assertEquals($result, [
                'field1' => 'john',
                'field2' => true,
                'key' => 'dummy 1',
                'range' => 'dummy 1']
        );

    }

    public function testAddItemValues()
    {
        $values = [
            'field1' => 'john',
            'field2' => true,
            'key' => 'dummy 1',
            'range' => 'dummy 1'
        ];

        $result = $this->getDriver()->addItem($values, $this->getClassMetadata());
        $this->assertInternalType('array',$result);

    }

}
