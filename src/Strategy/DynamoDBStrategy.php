<?php

namespace Eoko\ODM\Strategy;

use Aws\DynamoDb\Marshaler;
use Zend\Stdlib\Hydrator\Strategy\StrategyInterface;

class DynamoDBStrategy implements StrategyInterface
{
    protected $marshaller;

    /**
     * DynamoDBStrategy constructor.
     * @param array $options
     */
    public function __construct($options = ['ignore_invalid' => true])
    {
        $this->marshaller = new Marshaler($options);
    }

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param mixed $value The original value.
     * @return mixed Returns the value that should be extracted.
     */
    public function extract($value)
    {
        return $this->marshaller->unmarshalItem($value);
    }

    /**
     * Converts the given value so that it can be hydrated by the hydrator.
     *
     * @param mixed $value The original value.
     * @return mixed Returns the value that should be hydrated.
     */
    public function hydrate($value)
    {
        return $this->marshaller->marshalItem($value);
    }


}