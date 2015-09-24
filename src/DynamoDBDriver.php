<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Eoko\ODM\DocumentManager\Driver\DriverInterface;
use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Eoko\ODM\DocumentManager\Metadata\FieldInterface;
use Eoko\ODM\Metadata\Annotation\Document;

class DynamoDBDriver implements DriverInterface
{

    /** @var  DynamoDbClient */
    protected $client;

    /** @var  ClassMetadata */
    protected $classMetadata;

    /** @var string */
    protected $tableName;

    /**
     * @see http://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_AttributeValue.html
     * @var array
     */
    protected $typeMap = [
        'string' => 'S',
        'boolean' => 'BOOL',
        'number' => 'N',
        'null' => 'NULL',
    ];

    protected $options;
    
    protected $prefix = 'default_';

    /**
     * @param $options
     */
    public function __construct($options)
    {
        $this->options = $options;
        
        if(isset($options['prefix']) && is_string($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }
    }

    public function setClient(DynamoDbClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return null
     * @throws \Exception
     */
    public function addItem(array $values, ClassMetadata $classMetadata)
    {
        $item = $this->getItemValues($values, $classMetadata);
        $args = ['TableName' => $this->getTableName($classMetadata), 'Item' => $item];

        return $this->commit('putItem', $args);
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function getItemValues(array $values, ClassMetadata $classMetadata)
    {
        $mapped = array_map(function ($items) use ($values) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item instanceof FieldInterface) {
                        if (isset($values[$item->getName()])) {
                            $value = $values[$item->getName()];

                            if($item->getType() == 'number') {
                                $value = (string) $value;
                            } elseif(empty($value) && $item->getType() !== 'number') {
                                return;
                            }

                            return [$this->mapTypeField($item->getType()) => $value];
                        }
                    }
                }
            }
        }, array_intersect_key($classMetadata->getFields(), $values));

        return array_filter($mapped);
    }

    /**
     * @param string $typeName
     * @return string
     */
    private function mapTypeField($typeName)
    {
        return isset($this->typeMap[$typeName]) ? $this->typeMap[$typeName] : 'S';
    }

    protected function commit($command, $args)
    {
        return $this->client->$command($args);
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array|null
     * @throws \Exception
     */
    public function getItem(array $values, ClassMetadata $classMetadata)
    {
        $identifier = $this->getKeyValues($values, $classMetadata);
        $result = $this->commit('getItem', ['TableName' => $this->getTableName($classMetadata), 'Key' => $identifier]);
        return $this->cleanResult($result->get('Item'));
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function getKeyValues(array $values, ClassMetadata $classMetadata)
    {
        return array_map(function ($item) use ($values, $classMetadata) {
            if (!isset($values[$item['name']])) {
                throw new MissingIdentifierException('The field `' . $item['name'] . '` is mandatory');
            }
            return [$this->mapTypeField($item['type']) => $values[$item['name']]];
        }, $classMetadata->getIdentifier());
    }

    /**
     * @param $result
     * @return array|null
     */
    private function cleanResult($result)
    {
        return (is_array($result)) ? (new Marshaler())->unmarshalItem($result) : null;
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return ArrayCollection
     * @throws \Exception
     */
    public function findAll(ClassMetadata $classMetadata)
    {
        $result = $this->commit('scan', ['TableName' => $this->getTableName($classMetadata)]);
        $items = $result->get('Items');

        return array_map(function ($item) {
            return $this->cleanResult($item);
        }, $items);
    }

    /**
     * @todo handle more criteria, only where can be used
     * @param Criteria $criteria
     * @param int $limit
     * @param ClassMetadata $classMetadata
     * @return array
     * @throws \Exception
     */
    public function findBy(Criteria $criteria, $limit = null, ClassMetadata $classMetadata)
    {
        $expression = $criteria->getWhereExpression()->visit(new QueryBuilder(), $classMetadata);
        $tokens = isset($expression['tokens']) ? $expression['tokens'] : [];

        $tokens = array_map(function ($item) use ($classMetadata) {
            $type = $this->mapTypeField($classMetadata->getTypeOfField($item['field']));
            return [$type => $item['value']];
        }, $tokens);

        $request = [
            'TableName' => $this->getTableName($classMetadata),
            'FilterExpression' => $expression['expression'],
            'ExpressionAttributeValues' => $tokens,
        ];

        if ($limit) {
            $request['limit'] = $limit;
        }

        $result = $this->commit('scan', $request);

        return array_map(function ($item) {
            return $this->cleanResult($item);
        }, $result->get('Items'));
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return null
     * @throws \Exception
     */
    public function updateItem(array $values, ClassMetadata $classMetadata)
    {
        $item = $this->getItemValues($values, $classMetadata);
        $identifier = $this->getKeyValues($values, $classMetadata);
        $expressionAttribute = [];
        $updateExpression = [];

        foreach (array_diff_key($item, $identifier) as $key => $item) {
            $expressionAttribute[':' . $key] = $item;
            $updateExpression[] = $key . ' = :' . $key;
        };

        return $this->commit(
            'updateItem',
            [
                'TableName' => $this->getTableName($classMetadata),
                'Key' => $identifier,
                'UpdateExpression' => 'SET ' . implode(', ', $updateExpression),
                'ExpressionAttributeValues' => $expressionAttribute
            ]
        );
    }
    
    protected function getTableName($classMetadata) {
        return $this->prefix . $classMetadata->getDocument()->getTable();
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return null
     * @throws \Exception
     */
    public function deleteItem(array $values, ClassMetadata $classMetadata)
    {
        $identifier = $this->getKeyValues($values, $classMetadata);
        return $this->commit('deleteItem', ['TableName' => $this->getTableName($classMetadata), 'Key' => $identifier]);
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return null
     */
    public function createTable(ClassMetadata $classMetadata)
    {
        $attributesList = [];
        $model = ['AttributeDefinitions' => [], 'KeySchema' => [], 'ProvisionedThroughput' => [
            'ReadCapacityUnits' => 1,
            'WriteCapacityUnits' => 1
        ]];

        $metadata = $classMetadata->getClass();

        if(isset($metadata['Eoko\ODM\Metadata\Annotation\GlobalSecondaryIndexes'])) {
            $model['GlobalSecondaryIndexes'] = [];
            $indexes = $metadata['Eoko\ODM\Metadata\Annotation\GlobalSecondaryIndexes']->indexes;
            foreach($indexes as $key => $index) {

                $keys = [];

                foreach($index['keys'] as $item) {
                    $keys[] = ['AttributeName' => $item['name'], 'KeyType' => $item['type']];
                    $attributesList[$item['name']] = ['AttributeName' => $item['name'], 'AttributeType' => $this->mapTypeField($classMetadata->getTypeOfField($item['name']))];
                }


                $model['GlobalSecondaryIndexes'][$key]['IndexName'] = $index['name'];
                $model['GlobalSecondaryIndexes'][$key]['KeySchema'] = $keys;
                $model['GlobalSecondaryIndexes'][$key]['Projection'] = ['ProjectionType' => $index['projection']['projection-type']];
                $model['GlobalSecondaryIndexes'][$key]['ProvisionedThroughput'] = [
                    'ReadCapacityUnits' => 1,
                    'WriteCapacityUnits' => 1
                ];

            }
        }

        foreach($classMetadata->getIdentifier() as $key => $field) {
                $attributesList[$key] = [
                    'AttributeName' => $field['name'],
                    'AttributeType' => $this->mapTypeField($field['type'])
                ];

                $model['KeySchema'][] = [
                    'AttributeName' => $field['name'],
                    'KeyType' => $field['key']
                ];
        }

        $model['AttributeDefinitions'] = array_values($attributesList);
        $model['TableName'] = $this->getTableName($classMetadata);

        return $this->commit('createTable', $model);
    }

    /**
     *
     * @param ClassMetadata $classMetadata
     * @return null
     */
    public function deleteTable(ClassMetadata $classMetadata)
    {
        return $this->commit('deleteTable', ['TableName' => $this->getTableName($classMetadata)]);
    }

}
