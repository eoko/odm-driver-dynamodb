<?php

namespace Eoko\ODM\Driver\DynamoDB;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Eoko\ODM\DocumentManager\Driver\DriverInterface;
use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;

class DynamoDBDriver implements DriverInterface
{

    /** @var  DynamoDbClient */
    protected $client;

    /** @var  ClassMetadata */
    protected $classMetadata;

    /** @var string */
    protected $tableName = 'default';

    /**
     * @see http://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_AttributeValue.html
     * @var array
     */
    protected $typeMap = [
        'string' => 'S',
        'boolean' => 'BOOL',
        'null' => 'NULL',
    ];

    /**
     * @param DynamoDbClient $client
     */
    public function __construct(DynamoDbClient $client)
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
        return $this->commit('putItem', ['TableName' => $classMetadata->getDocument()->getTable(), 'Item' => $item]);
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
        $result = $this->commit('getItem', ['TableName' => $classMetadata->getDocument()->getTable(), 'Key' => $identifier]);
        return $this->cleanResult($result->get('Item'));
    }

    /**
     * @param ClassMetadata $classMetadata
     * @return ArrayCollection
     * @throws \Exception
     */
    public function findAll(ClassMetadata $classMetadata)
    {
        $result = $this->commit('scan', ['TableName' => $classMetadata->getDocument()->getTable()]);
        $items = $result->get('Items');

        return array_map(function($item) {
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

        $tokens = array_map(function($item) use ($classMetadata) {
            $type = $this->mapTypeField($classMetadata->getTypeOfField($item['field']));
            return [$type => $item['value']];
        }, $tokens);

        $request = array(
            'TableName' => $classMetadata->getDocument()->getTable(),
            'FilterExpression' => $expression['expression'],
            'ExpressionAttributeValues' => $tokens,
        );

        if($limit) {
            $request['limit'] = $limit;
        }

        $result = $this->commit('scan', $request);

        return array_map(function($item) {
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
                'TableName' => $classMetadata->getDocument()->getTable(),
                'Key' => $identifier,
                'UpdateExpression' => 'SET ' . implode(', ', $updateExpression),
                'ExpressionAttributeValues' => $expressionAttribute
            ]
        );
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
        return $this->commit('deleteItem', ['TableName' => $classMetadata->getDocument()->getTable(), 'Key' => $identifier]);
    }

    protected function commit($command, $args)
    {
        try {
            $result = $this->client->$command($args);
        } catch (DynamoDbException $exception) {
            $result = $this->mapDynamoDbExceptionResult($exception);
        }
        return $result;
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
     * @param DynamoDbException $exception
     * @return null
     */
    private function mapDynamoDbExceptionResult(DynamoDbException $exception)
    {
        switch ($exception->getAwsErrorCode()) {
            case 'ResourceNotFoundException' :
                return;
                break;
            default :
                throw $exception;
        }
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
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function getKeyValues(array $values, ClassMetadata $classMetadata)
    {
        return array_map(function ($item) use ($values, $classMetadata) {
            if(!isset($values[$item['name']])) {
                throw new MissingIdentifierException( 'The field of type `' . $item['name'] . '` is mandatory');
            }
            return [$this->mapTypeField($item['type']) => $values[$item['name']]];
        }, $classMetadata->getIdentifier());
    }
    /**
     * @param array $values
     * @param ClassMetadata $classMetadata
     * @return array
     */
    private function getItemValues(array $values, ClassMetadata $classMetadata)
    {
        $mapped = array_map(function ($items) use ($values) {
            if(is_array($items)) {
                foreach ($items as $item) {
                    if ($item instanceof AbstractField) {
                        if (isset($values[$item->name]) && !empty($values[$item->name]) ) {
                            return [$this->mapTypeField($item->type) => $values[$item->name]];
                        }
                    }
                }
            }
        }, array_intersect_key($classMetadata->getFields(), $values));

        return array_filter($mapped);
    }
}