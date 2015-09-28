<?php

namespace Eoko\ODM\Driver\DynamoDB\Test\Entity;

use Eoko\ODM\Metadata\Annotation\Document;
use Eoko\ODM\Metadata\Annotation\Index;
use Eoko\ODM\Metadata\Annotation\Number;
use Eoko\ODM\Metadata\Annotation\StringField;
use Eoko\ODM\Metadata\Annotation\DateTime;
use Eoko\ODM\Metadata\Annotation\KeySchema;
use Eoko\ODM\Metadata\Annotation\Boolean;
use Zend\Stdlib\ArraySerializableInterface;

/**
 * @Document(table="oauth_users", provision={"ReadCapacityUnits" : 1, "WriteCapacityUnits" : 1})
 * @KeySchema(keys={"username" : "HASH"})
 * @Index(name="username_index", fields={"username" : "HASH"})
 */
class UserEntity implements ArraySerializableInterface
{

    /**
     * @StringField
     */
    protected $username;

    /**
     * @DateTime
     */
    protected $created_at;

    /**
     * @StringField
     */
    protected $email;

    /**
     * @Boolean
     */
    protected $email_verified;

    /**
     * @Number
     */
    protected $age;

    /**
     * @return mixed
     */
    public function getAge()
    {
        return $this->age;
    }

    /**
     * @param mixed $age
     */
    public function setAge($age)
    {
        $this->age = $age;
    }



    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getEmailVerified()
    {
        return $this->email_verified;
    }

    /**
     * @param mixed $email_verified
     */
    public function setEmailVerified($email_verified)
    {
        $this->email_verified = $email_verified;
    }


    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * @param $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * Exchange internal values from provided array
     *
     * @param  array $array
     * @return void
     */
    public function exchangeArray(array $array)
    {
        $this->age = (isset($array['age'])) ? $array['age'] : null;
        $this->username = (isset($array['username'])) ? $array['username'] : null;
        $this->email = (isset($array['email'])) ? $array['email'] : null;
        $this->created_at = (isset($array['created_at'])) ? $array['created_at'] : null;
        $this->email_verified = (isset($array['email_verified'])) ? $array['email_verified'] : null;
    }

    /**
     * Return an array representation of the object
     *
     * @return array
     */
    public function getArrayCopy()
    {
        return array_filter([
            'age' => $this->getAge(),
            'username' => $this->getUsername(),
            'email' => $this->getEmail(),
            'email_verified' => $this->getEmailVerified(),
            'created_at' => $this->getCreatedAt()
        ], function ($var) {
            return (!is_null($var));
        });
    }
}
