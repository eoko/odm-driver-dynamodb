<?php

namespace Eoko\ODM\Driver\DynamoDB\Test\Entity;

use Eoko\ODM\Metadata\Annotation\Index;
use Eoko\ODM\Metadata\Annotation\ParentClass;

/**
 * @ParentClass
 * @Index(name="username_email-verified_index", fields={"username" : "HASH", "email_verified" : "RANGE"})
 */
class IncompatibleEntity extends UserEntity
{
}
