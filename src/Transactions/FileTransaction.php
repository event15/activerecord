<?php

/**
 * This file is part of the beebot package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2014
 * @author Stephane HULARD <s.hulard@chstudio.fr>
 * @package BeeBot\Entity\Transactions
 */

namespace BeeBot\Entity\Transactions;

/**
 * Description of BaseTransaction
 * @package BeeBot\Entity\Transactions
 */
class FileTransaction implements TransactionInterface
{
	/**
	 * Transactionable entities
	 * @var resource
	 */
	protected $stream;

	/**
	 * Number of entities
	 * @var integer
	 */
	protected $nb;

	/**
	 * Number of bytes read in the stream
	 * @var integer
	 */
	protected $pos;

	/**
	 * The current line
	 * @var string
	 */
	protected $current;

	/**
	 * Initialize entity collection
	 */
	public function __construct() {
		$this->nb = $this->pos = 0;
		$this->stream = tmpfile();
		if( $this->stream === false ) {
			throw new \RuntimeException("Can't create tmp stream !!");
		}
	}

	public function __destruct() {
		fclose($this->stream);
	}

	public function count() {
		return $this->nb;
	}

	public function current() {
		if( $this->current == "" ) {
			$this->rewind();
		}
		return unserialize($this->current);
	}

	public function key() {
		return $this->pos;
	}

	public function next() {
		$this->pos += strlen($this->current);
		$this->current = fgets($this->stream);
	}

	public function rewind() {
		$this->pos = 0;
		fseek($this->stream, $this->pos);
		$this->current = fgets($this->stream);
	}

	public function valid() {
		return !feof($this->stream);
	}

	public function persist(\BeeBot\Entity\Entity $entity) {
		if( !$entity::isSerializable() ) {
			throw new \InvalidArgumentException('Entity given must be serializable when using FileTransaction (use SerializableEntity trait or Serializable interface...)');
		}

		$this->nb++;
		$s = serialize($entity).PHP_EOL;
		fseek($this->stream, 0, SEEK_END);
		fwrite($this->stream, $s);
		rewind($this->stream);
	}
}
