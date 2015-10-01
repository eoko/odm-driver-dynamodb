<?php
/**
 * Created by PhpStorm.
 * User: merlin
 * Date: 28/09/15
 * Time: 19:17
 */

namespace Eoko\ODM\Driver\DynamoDB\Transform;


use Eoko\ODM\DocumentManager\Metadata\ClassMetadata;
use Eoko\ODM\DocumentManager\Metadata\FieldInterface;
use Zend\Stdlib\Hydrator\AbstractHydrator;
use Zend\Stdlib\Hydrator\HydrationInterface;

class ValuesToDynamoDB {

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
     * Hydrate $object with the provided $data.
     *
     * @param  array $values
     * @param FieldInterface[] $expectedFields
     * @return array
     */
    public function transform(array $values, $expectedFields)
    {
        $item = array_map(function ($items) use ($values) {
            if (is_array($items)) {
                foreach ($items as $field) {
                    if ($field instanceof FieldInterface) {
                        return [$this->mapTypeField($field->getType()) => $this->mapToField($values[$field->getName()], $field)];
                    }
                }
            }
        }, array_intersect_key($expectedFields, $values));

        return $item;
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
     * @param $value
     * @param FieldInterface $field
     * @return bool|string
     * @throws \Exception
     */
    private function mapToField($value, FieldInterface $field) {
        switch($field->getType()) {
            case 'boolean' :
                return (boolean) $value;
            case 'string' :
                return (string) $value;
            case 'integer' :
                return (string) (integer) $value;
            case 'float' :
                return (string) (float) $value;
            case 'number' :
                return (string) (float) $value;
            default :
                throw new \Exception('The following type `' . $field->getType() . '` is not supported by the driver.');
        }
    }
}