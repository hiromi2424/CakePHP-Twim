<?php

/**
 * for DirectMessage API
 *
 * CakePHP 2.0
 * PHP version 5
 *
 * Copyright 2012, nojimage (http://php-tips.com/)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @version   2.0
 * @author    nojimage <nojimage at gmail.com>
 * @copyright 2012 nojimage (http://php-tips.com/)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   Twim
 * @since     File available since Release 2.0
 *
 * @link      https://dev.twitter.com/docs/api/1/get/direct_messages
 * @link      https://dev.twitter.com/docs/api/1/get/direct_messages/sent
 * @link      https://dev.twitter.com/docs/api/1/get/direct_messages/show/%3Aid
 * @link      https://dev.twitter.com/docs/api/1/post/direct_messages/destroy/%3Aid
 * @link      https://dev.twitter.com/docs/api/1/post/direct_messages/new
 *
 */
App::uses('TwimAppModel', 'Twim.Model');

/**
 *
 */
class TwimDirectMessage extends TwimAppModel {

	public $apiUrlBase = '1/direct_messages/';

	/**
	 * The model's schema. Used by FormHelper
	 *
	 * @var array
	 */
	protected $_schema = array(
		'id' => array('type' => 'integer', 'length' => '20'),
		'text' => array('type' => 'string', 'length' => '140'),
		'user_id' => array('type' => 'integer', 'length' => '20'),
		'screen_name' => array('type' => 'string', 'length' => '32'),
	);

	/**
	 * Validation rules for the model
	 *
	 * @var array
	 */
	public $validate = array(
		'text' => array(
			'notEmpty' => array(
				'rule' => 'notEmpty',
				'message' => 'Please enter some text',
			),
			'maxLength' => array(
				'rule' => array('maxLength', 140),
				'message' => 'Text cannot exceed 140 characters',
			),
		),
		'user_id' => array(
			'numeric' => array(
				'rule' => 'numeric',
				'message' => 'The ID of the user you are replying to should be numeric',
				'required' => false,
				'allowEmpty' => true,
			),
		),
	);

	/**
	 *
	 * @var array
	 */
	public $actsAs = array();

	/**
	 * Custom find types available on this model
	 *
	 * @var array
	 */
	public $findMethods = array(
		'receipt' => true,
		'sent' => true,
		'show' => true,
	);

	/**
	 * The custom find types that require authentication
	 *
	 * @var array
	 */
	public $findMethodsRequiringAuth = array(
		'receipt',
		'sent',
		'show',
	);

	/**
	 * The options allowed by each of the custom find types
	 *
	 * @var array
	 */
	public $allowedFindOptions = array(
		'receipt' => array('since_id', 'max_id', 'count', 'page', 'include_entities'),
		'sent' => array('since_id', 'max_id', 'count', 'page', 'include_entities'),
		'show' => array('id'),
	);

	/**
	 * DirectMessage API max number of count
	 *
	 * @var int
	 */
	public $maxCount = 200;

	/**
	 * The vast majority of the custom find types actually follow the same format
	 * so there was little point explicitly writing them all out. Instead, if the
	 * method corresponding to the custom find type doesn't exist, the options are
	 * applied to the model's request property here and then we just call
	 * parent::find('all') to actually trigger the request and return the response
	 * from the API.
	 *
	 * In addition, if you try to fetch a timeline that supports paging, but you
	 * don't specify paging params, you really want all tweets in that timeline
	 * since time imemoriam. But twitter will only return a maximum of 200 per
	 * request. So, we make multiple calls to the API for 200 tweets at a go, for
	 * subsequent pages, then merge the results together before returning them.
	 *
	 * Twitter's API uses a count parameter where in CakePHP we'd normally use
	 * limit, so we also copy the limit value to count so we can use our familiar
	 * params.
	 *
	 * @param string $type
	 * @param array $options
	 * @return mixed
	 */
	public function find($type, $options = array()) {
		if ($type === 'all') {
			$type = 'receipt';
		}

		if (in_array('count', $this->allowedFindOptions[$type])) {
			$defaults = array('count' => $this->maxCount, 'strict' => false);
			$options = array_merge($defaults, $options);

			if (!empty($options['limit']) && $options['limit'] <= $this->maxCount) {
				$options['count'] = $options['limit'];
			}
		}

		if (empty($options['page'])
			&& array_key_exists($type, $this->allowedFindOptions)
			&& in_array('page', $this->allowedFindOptions[$type])
			&& in_array('count', $this->allowedFindOptions[$type])) {
			$options['page'] = 1;
			$results = array();
			try {
				while (($page = $this->find($type, $options)) != false) {
					if (!empty($options['limit']) && count($results) >= $options['limit']) {
						break;
					}
					$results = array_merge($results, $page);
					$options['page']++;
				}
			} catch (RuntimeException $e) {
				if ($options['strict']) {
					throw $e;
				}
				$this->log($e->getMessage(), LOG_DEBUG);
			}
			return $results;
		}
		if (method_exists($this, '_find' . Inflector::camelize($type))) {
			return parent::find($type, $options);
		}

		$this->_setupRequest($type, $options);
		if (in_array($type, array('all', 'receipt'))) {
			$this->request['uri']['path'] = '1/direct_messages';
		}

		return parent::find('all', $options);
	}

	/**
	 * receipt
	 * -------------
	 *
	 *     TwitterDirectMessage::find('receipt', $options)
	 *
	 * @param $state string 'before' or 'after'
	 * @param $query array
	 * @param $results array
	 * @return mixed
	 * @access protected
	 * */
	protected function _findReceipt($state, $query = array(), $results = array()) {
		if ($state === 'before') {
			$type = 'receipt';
			$this->_setupRequest($type, $query);
			$this->request['uri']['path'] = '1/direct_messages';
			return $query;
		} else {
			return $results;
		}
	}

	/**
	 * show
	 * -------------
	 *
	 *     TwitterDirectMessage::find('show', $options)
	 *
	 * @param $state string 'before' or 'after'
	 * @param $query array
	 * @param $results array
	 * @return mixed
	 * @access protected
	 * */
	protected function _findShow($state, $query = array(), $results = array()) {
		if ($state === 'before') {

			if (empty($query['id']) && isset($query[0])) {
				$query['id'] = $query[0];
				unset($query[0]);
			}

			if (empty($query['id'])) {
				return $query;
			}

			$type = 'show';

			$this->_setupRequest($type, $query);

			$this->request['uri']['path'] = $this->apiUrlBase . $type . '/' . $query['id'];
			unset($this->request['uri']['query']['id']);
			unset($query['id']);

			return $query;
		} else {
			return $results;
		}
	}

	/**
	 * alias of find('show')
	 *
	 * @param string $id
	 * @return array
	 */
	public function findById($id) {
		return $this->find('show', $id);
	}

	/**
	 * alias of save
	 *
	 * @param mixed $data
	 * @param mixed $validate
	 * @param mixed $fieldList
	 * @return mixed
	 */
	public function send($data = null, $validate = true, $fieldList = array()) {
		return $this->save($data, $validate, $fieldList);
	}

	/**
	 * send new message
	 *
	 * @param mixed $data
	 * @param mixed $validate
	 * @param mixed $fieldList
	 * @return mixed
	 */
	public function save($data = null, $validate = true, $fieldList = array()) {
		if (isset($data[$this->alias])) {
			$data = $data[$this->alias];
		}

		$this->request = array(
			'uri' => array(
				'path' => '1/direct_messages/new',
			),
			'method' => 'POST',
			'auth' => true,
			'body' => $data,
		);
		$result = parent::save($data, $validate, $fieldList);
		if ($result && !empty($this->response['id_str'])) {
			$this->setInsertID($this->response['id_str']);
		}
		return $result;
	}

	/**
	 * Deletes a message
	 *
	 * @param integer $id Id of the tweet to be deleted
	 * @param boolean $cascade
	 * @return boolean
	 */
	public function delete($id = null, $cascade = true) {
		$this->request = array(
			'uri' => array(
				'path' => '1/direct_messages/destroy/' . $id,
			),
			'method' => 'POST',
			'auth' => true,
		);
		return parent::delete($id, $cascade);
	}

	/**
	 * Returns true if a status with the currently set ID exists.
	 *
	 * @return boolean True if such a status exists
	 * @access public
	 */
	public function exists() {
		if ($this->getID() === false) {
			return false;
		}
		$_request = $this->request;
		$result = $this->find('show', array('id' => $this->getID()));
		$this->request = $_request;
		return !empty($result);
	}

}