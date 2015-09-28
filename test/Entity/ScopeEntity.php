<?php
/**
 * Created by PhpStorm.
 * User: merlin
 * Date: 28/09/15
 * Time: 02:29
 */

namespace Eoko\ODM\Driver\DynamoDB\Test\Entity;

use Eoko\ODM\Metadata\Annotation\Document;
use Eoko\ODM\Metadata\Annotation\Index;
use Eoko\ODM\Metadata\Annotation\StringField;
use Eoko\ODM\Metadata\Annotation\KeySchema;
use Zend\Stdlib\ArraySerializableInterface;

/**
 * @Document(table="scope", provision={"ReadCapacityUnits" : 1, "WriteCapacityUnits" : 1})
 * @KeySchema(keys={"scope_name" : "HASH"})
 * @Index(name="is_default_index", fields={"is_default" : "HASH"})
 */
class ScopeEntity implements ArraySerializableInterface
{

    /**
     * @StringField
     */
    protected $scope_name;

    /**
     * @StringField
     */
    protected $is_default;

    /**
     * @return mixed
     */
    public function getScopeName()
    {
        return $this->scope_name;
    }

    /**
     * @param mixed $scope_name
     */
    public function setScopeName($scope_name)
    {
        $this->scope_name = $scope_name;
    }

    /**
     * @return mixed
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * @param mixed $is_default
     */
    public function setIsDefault($is_default)
    {
        $this->is_default = $is_default;
    }



    /**
     * Exchange internal values from provided array
     *
     * @param  array $array
     * @return void
     */
    public function exchangeArray(array $array)
    {
        $this->scope_name = (isset($array['scope_name'])) ? $array['scope_name'] : null;
        $this->is_default = (isset($array['is_default'])) ? $array['is_default'] : null;
    }

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return array_filter([
            'scope_name' => $this->getScopeName(),
            'is_default' => $this->getIsDefault(),
        ], function ($var) {
            return (!is_null($var));
        });
    }
}
