<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Doctrine\Common\Collections\Criteria;
use Eoko\ODM\DocumentManager\Driver\DriverInterface;
use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Eoko\ODM\DocumentManager\Metadata\FieldInterface;
use Zend\Log\Logger;
use Zend\Log\LoggerInterface;

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

    /**
     * @see http://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_AttributeValue.html
     * @var array
     */
    protected $keyTypeMap = [
        'binary' => 'B',
        'number' => 'N',
        'string' => 'S',
    ];

    protected $options;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $prefix = 'default_';

    /**
     * @param $options
     */
    public function __construct($options, $client, $logger = null)
    {
        $this->options = $options;
        $this->client = $client;

        if (isset($options['prefix']) && is_string($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }

        if ($logger instanceof LoggerInterface) {
            $this->logger = $logger;
        }
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
                foreach ($items as $field) {
                    if ($field instanceof FieldInterface) {
                        return $this->mapField($field, $values);
                    }
                }
            }
        }, array_intersect_key($classMetadata->getFields(), $values));

        return array_filter($mapped);
    }

    private function mapField($field, $values)
    {
        $value = $values[$field->getName()];

        if (is_null($value)) {
            return;
        }

        $type = $this->mapTypeField($field->getType());

        if ($field->getType() === 'number') {
            $value = (string) $value;
        }

        if ($field->getType() === 'boolean') {
            $value = (boolean) $value;
        }

        return [$type => $value];
    }

    /**
     * @param string $typeName
     * @return string
     */
    private function mapTypeField($typeName)
    {
        return isset($this->typeMap[$typeName]) ? $this->typeMap[$typeName] : 'S';
    }

    /**
     * @param string $typeName
     * @return string
     */
    private function mapKeyTypeField($typeName)
    {
        return isset($this->keyTypeMap[$typeName]) ? $this->keyTypeMap[$typeName] : null;
    }

    protected function commit($command, $args)
    {
        if ($this->logger) {
            $this->logger->debug(__CLASS__ . ' >> ' . $command, $args);
        }
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
     * @return array
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

    protected function getTableName($classMetadata)
    {
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

        if (count($classMetadata->getIndexes()) > 0) {
            $model['GlobalSecondaryIndexes'] = [];
            foreach ($classMetadata->getIndexes() as $index) {
                $keys = [];

                foreach ($index['fields'] as $key => $type) {
                    $keys[] = ['AttributeName' => $key, 'KeyType' => $type];
                    $attributeType = $this->mapKeyTypeField($classMetadata->getTypeOfField($key));

                    if (is_null($attributeType)) {
                        throw new IncompatibleTypeException('We cannot create a GSI with key `' . $key . '` and type `' . $classMetadata->getTypeOfField($key) . '`.');
                    }

                    $attributesList[$key] = ['AttributeName' => $key, 'AttributeType' => $attributeType];
                }

                $model['GlobalSecondaryIndexes'][] = [
                    'IndexName' => $index['name'],
                    'KeySchema' => $keys,
                    'Projection' => [
                        'ProjectionType' => 'ALL'
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 1,
                        'WriteCapacityUnits' => 1
                    ]
                ];
            }
        }

        foreach ($classMetadata->getIdentifier() as $key => $field) {
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

    /**
     * @param ClassMetadata $classMetadata
     * @return boolean
     */
    public function isTable(ClassMetadata $classMetadata)
    {
        return $this->getTableStatus($classMetadata) !== 'DELETING';
    }

    public function getTableStatus(ClassMetadata $classMetadata)
    {
        try {
            return $this->commit('describeTable', ['TableName' => $this->getTableName($classMetadata)])->get('Table')['TableStatus'];
        } catch (\Exception $e) {
            return false;
        }
    }
}
