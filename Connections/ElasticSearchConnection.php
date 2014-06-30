<?php

/**
 * This file is part of the beebot package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2014
 * @author Stephane HULARD <s.hulard@chstudio.fr>
 * @package BeeBot\Entity\Connections
 */

namespace BeeBot\Entity\Connections;

use BeeBot\Tools\Native\JsonTransformer;
use Bee4\Http\Client;
use Bee4\Http\Message\Request\AbstractRequest;

/**
 * ElasticSearch connection adapter
 * Allow to perform operations on ES indexes
 * @package BeeBot\Entity\Connections
 */
class ElasticSearchConnection extends AbstractConnection
{
	/**
	 * Http client used to communicate with ES
	 * @var \Bee4\Http\Client
	 */
	protected $client;
	
	/**
	 * Initialize ES adapter
	 * @param string $url ElasticSearch index URL
	 */
	public function __construct($url) {
		if(strrpos($url,'/')!==strlen($url)-1) {
			$url .= "/";
		}
		$this->client = new Client($url);
		
		//Register events for debug purpose and performance check
		$this->client->register(Client::ON_REQUEST, function(AbstractRequest $request) {
			$this->dispatch(ConnectionEvent::REQUEST, new ConnectionEvent($request));
		});
		$this->client->register(Client::ON_ERROR, function(\Exception $error) {
			$this->dispatch(ConnectionEvent::ERROR, new ConnectionEvent($error));
		});
	}
	
	public function countBy($type, $term, $value) {
		$response = $this->run($type, ["query" => self::buildQuery($term, $value)], '_count');
		return $response['count'];
	}
	
	public function fetchBy($type, $term, $value) {
		$response = $this->run($type, [
			"query" => self::buildQuery($term, $value), 
			"fields" => ['_source','_parent','_timestamp']
		]);
		
		return $this->extractResults($response);
	}
	
	public function raw($type, $query) {
		$response = $this->run($type, $query);
		return $this->extractResults($response);
	}
	
	public function save(\BeeBot\Entity\Entity $entity) {
		$url = $entity::getType().'/'.$entity->uid;
		if( $entity::isChild() && $entity->getParent() !== null ) {
			if( !$entity->getParent()->isPersisted() ) {
				throw new \RuntimeException('Parent entity is not a persisted one!');
			}
			
			$url.='?parent='.$entity->getParent()->uid;
		}
		
		$response = $this->client
			->put($url)
			->setBody(JsonTransformer::encode($entity))
			->send()->json();
		
		try {
			return $this->checkErrors($response);
		} catch( \Exception $error ) {
			$event = new \BeeBot\Event\ExceptionEvent($error);
			$this->dispatch($event::WARNING, $event);
			return false;
		}
	}
	
	public function delete(\BeeBot\Entity\Entity $entity) {
		$response = $this->client
			->delete($entity::getType().'/'.$entity->uid)
			->send()->json();
		
		try {
			$this->checkErrors($response);
			if( $response['found'] === false ) {
				throw new \InvalidArgumentException('Given entity does not exists in ElasticSearch!!');
			}
			return $response['found'];
		} catch( \Exception $error ) {
			$event = new \BeeBot\Event\ExceptionEvent($error);
			$this->dispatch($event::WARNING, $event);
			return false;
		}
	}
	
	/**
	 * Make a search request on requested documents
	 * @param string $type Document type to be searched
	 * @param array $request Request array to be used for search
	 * @param string $endpoint ElasticSearch endpoint used for the current request
	 * @return array|bool|float|int|string
	 * @throws \RuntimeException
	 */
	protected function run( $type, array $request, $endpoint = "_search" ) {
		$post = $this->client->post($type.'/'.$endpoint.'?pretty');

		//Always return Parent and timestamp property!!
		$json = JsonTransformer::encode($request);
		if( $json === false ) {
			throw new \RuntimeException('An error occured during JSON encoding of the given parameters: '.$request);
		}
		$response = $post->setBody($json)->send()->json();
		$this->checkErrors($response);
			
		//It's a search answer, we extract only the needed document
		return $response;
	}
	
	/**
	 * Check if the current response contain invalid codes
	 * @param array $response
	 * @return boolean
	 * @throws \RuntimeException
	 */
	protected function checkErrors( array $response ) {
		if( isset($response['error']) || isset($response['status'])&&$response['status']!==200 ) {
			throw new \RuntimeException('Current request give an invalid response: '.  print_r($response, true));
		}
		if( isset($response['_shards']) && $response['_shards']['failed'] > 0 ) {
			throw new \RuntimeException('Some shards failed to give result: '.  print_r($response, true));
		}
		
		return true;
	}
		
	/**
	 * Extract data from given ES result (hits array)
	 * Make some adjustement (uid) and prepare for Entity building
	 * @param array $response
	 * @return array
	 */
	protected function extractResults(array $response) {
		$result = [];
		foreach( $response['hits']['hits'] as $hit ) {
			$hit['_source']['uid'] = $hit['_id'];
			$result[] = $hit['_source'];
		}
		return $result;
	}
	
	/**
	 * Compute a valid elasticsearch query from term and value
	 * This method is a helper for search methods (count, fetch, ...)
	 * @param Mixed $term The terme to be search
	 * @param Mixed $value The value to be searched
	 * @return Array
	 */
	private static function buildQuery( $term, $value ) {
		//If value is an array of 2 elements
		if( is_array($value) ) {
			if( count($value) == 2 ) {
				return array( 'range' => array( $term => array( 'from' => $value[0], 'to' => $value[1] ) ) );
			} elseif( count($value) === 1 ) {
				return self::buildQuery($term, $value[0]);
			}
		} elseif( is_string( $value ) ) {
			$parts = array();
			//Regexp or wildcard or prefix queries
			// => Warning about which Regexp are expensives
			// => Wildcard used to retrieve items by wildcard * match multi characters and ? match single ones
			// => Prefix used to search terms that starts with $aParts[1]
			if( preg_match('/^(regexp|wildcard|prefix):(.*)$/', $value, $parts ) === 1 ) {
				return array( $parts[1] => array($term => $parts[2]) );
			//Specific range queries lesser than, greater than, lesser or equal and greater or equal
			} elseif( preg_match('/^(lt|gt|gte|lte):(.*)$/', $value, $parts ) === 1 ) {
				return array(
					'range' => array( $term => array($parts[1] => $parts[2]) )
				);
			//Or complete range with specific values given as JSON array
			} elseif( preg_match('/^range:(.*)$/', $value, $parts) === 1 ) {
				$dates = json_decode($parts[1]);
				return self::buildQuery($term, $dates===null?array($parts[1],$parts[1]):$dates);
			}
		}

		//Standard one is the term query
		return array( 'term' => array( $term => $value ) );
	}
}