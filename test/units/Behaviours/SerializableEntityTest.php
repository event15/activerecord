<?php

/**
 * This file is part of the bee4/activerecord package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author Stephane HULARD <s.hulard@chstudio.fr>
 * @package BeeBot\Entity\Tests\Behaviours
 */

namespace BeeBot\Entity\Tests\Behaviours;

require_once __DIR__.'/../../samples/SampleSerializableEntity.php';
require_once __DIR__.'/../../samples/SampleMultipleBehavioursEntity.php';

use \BeeBot\Entity\Tests\Samples;

/**
 * Check the Serializable entity behaviour
 * @package BeeBot\Entity\Tests\Behaviours
 */
class SerializableEntityTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Samples\SampleSerializableEntity
     */
    private $object;

    public function setUp()
    {
        $this->object = new Samples\SampleSerializableEntity;
    }

    public function testBehaviour()
    {
        $this->assertTrue(Samples\SampleSerializableEntity::isSerializable());
        $this->object->truite = "truite";
        $this->object->editable = ";)";

        $new = unserialize(serialize($this->object));

        $this->assertEquals($this->object, $new);
        $this->assertEquals("truite", $new->truite);
        $this->assertEquals(";)", $new->editable);
        $this->assertEquals($this->object->isNew(), $new->isNew());

        $connexion = $this->getMock("\BeeBot\Entity\Connections\AbstractConnection");
        $connexion->method("save")->willReturn(true);
        $connexion->method("delete")->willReturn(true);
        \BeeBot\Entity\ActiveRecord::setConnection($connexion);

        $this->object->save();
        $saved = unserialize(serialize($this->object));
        $this->assertEquals($this->object->isPersisted(), $saved->isPersisted());

        $this->object->delete();
        $deleted = unserialize(serialize($this->object));
        $this->assertEquals($this->object->isDeleted(), $deleted->isDeleted());
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testInvalidStateSerialize()
    {
        $stateProperty = (new \ReflectionClass("\BeeBot\Entity\Entity"))->getProperty('state');
        $stateProperty->setAccessible('true');
        $stateProperty->setValue($this->object, -1);

        serialize($this->object);
    }

    public function testParentSerialize()
    {
        $child = new Samples\SampleMultipleBehavioursEntity();
        $parent = new Samples\SampleSerializableEntity();
        $child->setParent($parent);

        $parent->editable = "I'm the parent";
        $child->editable = "I'm the child";

        $undead = unserialize(serialize($child));

        $this->assertEquals($parent->getUID(), $undead->getParent()->getUID());
        $this->assertEquals($child->getUID(), $undead->getUID());
        $this->assertEquals($parent->editable, $undead->getParent()->editable);
        $this->assertEquals($child->editable, $undead->editable);
    }
}
