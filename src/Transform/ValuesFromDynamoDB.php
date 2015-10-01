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

class ValuesFromDynamoDB {

    /**
     * Transform $values to a dynamoDB item
     *
     * @param  array $values
     * @param FieldInterface[] $expectedFields
     * @return array
     */
    public function transform(array $values, $expectedFields)
    {
        return array_map(function ($items) use ($values) {
            if (is_array($items)) {
                foreach ($items as $field) {
                    if ($field instanceof FieldInterface) {
                        return $this->mapToValue(array_pop($values[$field->getName()]), $field->getType());
                    }
                }
            }
        }, array_intersect_key($expectedFields, $values));
    }

    private function mapToValue($value, $type) {
        switch($type) {
            case 'boolean' :
                return (boolean) $value;
            case 'string' :
                return (string) $value;
            case 'integer' :
                return (integer) $value;
            case 'float' :
                return (float) $value;
            case 'number' :
                return (float) $value;
            default :
                throw new \Exception('The following type `' . $type . '` is not supported by the driver.');
        }
    }

}