<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Doctrine\Common\Collections\Criteria;
use Eoko\ODM\DocumentManager\Driver\DriverInterface;
use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Eoko\ODM\Driver\DynamoDB\Strategy\DynamoDBStrategy;
use Exception;
use Zend\Log\Logger;
use Zend\Log\LoggerInterface;

class DynamoDBDriver implements DriverInterface
{

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

    /** @var  DynamoDbClient */
    protected $client;

    /**
     * @see http://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_AttributeValue.html
     * @var array
     */
    protected $keyTypeMap = [
        'binary' => 'B',
        'number' => 'N',
        'string' => 'S',
    ];

    /** @var  array */
    protected $options;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var string  */
    protected $prefix = 'default_';

    /** @var DynamoDBStrategy */
    protected $strategy;

    /**
     * @param $options
     */
    public function __construct($options, $client, $logger = null)
    {
        $this->options = $options;
        $this->client = $client;
        $this->strategy = new DynamoDBStrategy();

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
     * @return array
     * @throws Exception
     */
    public function addItem(array $values, ClassMetadata $classMetadata)
    {
        if (!$classMetadata->hasIndex($values)) {
            throw new MissingIdentifierException('The following field [' . implode(', ', $classMetadata->getIdentifierFieldNames()) . '] are mandatories.');
        }

        $item = $this->strategy->hydrate($values);
        $args = ['TableName' => $this->getTableName($classMetadata), 'Item' => $item];

        if (!$this->commit('putItem', $args)) {
            throw new Exception('Somthing wrong');
        }

        return $values;
    }

    /**
     * @param array $identifiers
     * @param ClassMetadata $classMetadata
     * @return array|null
     * @throws Exception
     */
    public function getItem(array $identifiers, ClassMetadata $classMetadata)
    {
        $identifiers = $this->getKeyValues($identifiers, $classMetadata);
        $result = $this->commit('getItem', ['TableName' => $this->getTableName($classMetadata), 'Key' => $identifiers]);
        $item = $result->get('Item');

        if (!$item) {
            return;
        }

        return $this->strategy->extract($item);
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return array
     * @throws Exception
     */
    public function findAll(ClassMetadata $classMetadata)
    {
        $result = $this->commit('scan', ['TableName' => $this->getTableName($classMetadata)]);
        $items = $result->get('Items');

        return array_map(function ($item) {
            return $this->strategy->extract($item);
        }, $items);
    }

    /**
     * @todo handle more criteria, only where can be used
     * @param Criteria $criteria
     * @param ClassMetadata $classMetadata
     * @return array
     * @internal param int $limit
     */
    public function findBy(Criteria $criteria, ClassMetadata $classMetadata)
    {
        $expression = $criteria->getWhereExpression()->visit(new QueryBuilder(), $classMetadata);
        $tokens = isset($expression['tokens']) ? $expression['tokens'] : [];

        $tokens = array_map(function ($item) use ($classMetadata) {
            $type = $this->mapTypeField($classMetadata->getTypeOfField($item['field']));
            $value = $item['value'];
            if($type === 'N') {
                $value = (string) $value;
            }
            return [$type => $value];
        }, $tokens);

        $request = [
            'TableName' => $this->getTableName($classMetadata),
            'FilterExpression' => $expression['expression'],
            'ExpressionAttributeValues' => $tokens,
        ];

        $result = $this->commit('scan', $request);

        return array_map(function ($item) {
            return $this->strategy->extract($item);
        }, $result->get('Items'));
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return null
     * @throws Exception
     */
    public function updateItem(array $identifiers, array $values, ClassMetadata $classMetadata)
    {
        $item = $this->strategy->hydrate($values);
        $identifierFields = $this->getKeyValues($identifiers, $classMetadata);

        $expressionAttribute = [];
        $updateExpression = [];
        $expressionAttributeName = [];

        foreach (array_diff_key($item, $identifierFields) as $key => $item) {
            $prefix_key = 'prefix_' . $key;
            $expressionAttributeName['#' . $prefix_key] = $key;
            $expressionAttribute[':' . $prefix_key] = $item;
            $updateExpression[] = '#' . $prefix_key . ' = :' . $prefix_key;
        };

        $result = $this->commit(
            'updateItem',
            [
                'TableName' => $this->getTableName($classMetadata),
                'Key' => $identifierFields,
                'UpdateExpression' => 'SET ' . implode(', ', $updateExpression),
                'ExpressionAttributeValues' => $expressionAttribute,
                'ExpressionAttributeNames' => $expressionAttributeName
            ]
        );

        return $result ? $values : false;
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return boolean
     * @throws Exception
     */
    public function deleteItem(array $identifiers, ClassMetadata $classMetadata)
    {
        $result = $this->commit('deleteItem', ['TableName' => $this->getTableName($classMetadata), 'Key' => $this->getKeyValues($identifiers, $classMetadata)]);
        return $result ? true : false;
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

        $indexes = $classMetadata->getIndexes();
        // We prevent that index from primary keys are not included
        unset($indexes[$classMetadata->buildHash($classMetadata->getIdentifierFieldNames())]);


        if (count($indexes) > 0) {
            $model['GlobalSecondaryIndexes'] = [];

            foreach ($indexes as $index) {
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

        return ($this->commit('createTable', $model)) ? true : false;
    }

    /**
     *
     * @param ClassMetadata $classMetadata
     * @return null
     */
    public function deleteTable(ClassMetadata $classMetadata)
    {
        return ($this->commit('deleteTable', ['TableName' => $this->getTableName($classMetadata)])) ? true : false;
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
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return string
     */
    public function getTableName(ClassMetadata $classMetadata)
    {
        return $this->prefix . $classMetadata->getDocument()->getTable();
    }

    /**
     * @param string $command
     * @param [] $args
     * @return mixed
     */
    protected function commit($command, array $args)
    {
        if ($this->logger) {
            $this->logger->debug(__CLASS__ . ' >> ' . $command, $args);
        }
        return $this->client->$command($args);
    }

    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array
     */
    protected function getKeyValues(array $values, ClassMetadata $classMetadata)
    {
        return array_map(function ($item) use ($values, $classMetadata) {
            if (!isset($values[$item['name']])) {
                throw new MissingIdentifierException('The field `' . $item['name'] . '` is mandatory');
            }
            return [$this->mapTypeField($item['type']) => $values[$item['name']]];
        }, $classMetadata->getIdentifier());
    }

    /**
     * @param string $typeName
     * @return string
     */
    protected function mapTypeField($typeName)
    {
        return isset($this->typeMap[$typeName]) ? $this->typeMap[$typeName] : 'S';
    }

    /**
     * @param string $typeName
     * @return string
     */
    protected function mapKeyTypeField($typeName)
    {
        return isset($this->keyTypeMap[$typeName]) ? $this->keyTypeMap[$typeName] : null;
    }
}
