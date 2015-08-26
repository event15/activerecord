<?php

/**
 * This file is part of the bee4/activerecord package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author Stephane HULARD <s.hulard@chstudio.fr>
 * @package BeeBot\Entity\Tests\Samples
 */

namespace BeeBot\Entity\Tests\Samples;

require_once __DIR__.'/SampleEntity.php';

/**
 * Sample to test Serializable behaviour
 * @package BeeBot\Entity\Tests\Samples
 */
class SampleSerializableEntity extends SampleEntity implements \Serializable
{
    use \BeeBot\Entity\Behaviours\SerializableEntity;
}
