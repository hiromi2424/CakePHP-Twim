<?php

/**
 * for Trend API
 *
 * PHP versions 5
 *
 * Copyright 2011, nojimage (http://php-tips.com/)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @version   1.0
 * @author    nojimage <nojimage at gmail.com>
 * @copyright 2011 nojimage (http://php-tips.com/)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   twim
 * @since   　File available since Release 1.0
 * @see       http://dev.twitter.com/doc/get/statuses/public_timeline
 * @see       http://dev.twitter.com/doc/get/statuses/home_timeline
 * @see       http://dev.twitter.com/doc/get/statuses/friends_timeline
 * @see       http://dev.twitter.com/doc/get/statuses/user_timeline
 * @see       http://dev.twitter.com/doc/get/statuses/mentions
 * @see       http://dev.twitter.com/doc/get/statuses/retweeted_by_me
 * @see       http://dev.twitter.com/doc/get/statuses/retweeted_to_me
 * @see       http://dev.twitter.com/doc/get/statuses/retweets_of_me
 * @see       http://dev.twitter.com/doc/get/statuses/show/:id
 * @see       http://dev.twitter.com/doc/get/statuses/retweets/:id
 * @see       http://dev.twitter.com/doc/get/statuses/:id/retweeted_by
 * @see       http://dev.twitter.com/doc/get/statuses/:id/retweeted_by/ids
 * @see       http://dev.twitter.com/doc/post/statuses/update
 * @see       http://dev.twitter.com/doc/post/statuses/retweet/:id
 * @see       http://dev.twitter.com/doc/post/statuses/destroy/:id
 *
 */
class TwimStatus extends TwimAppModel {

    /**
     * The model's schema. Used by FormHelper
     *
     * @var array
     */
    public $_schema = array(
        'id' => array('type' => 'integer', 'length' => '11'),
        'text' => array('type' => 'string', 'length' => '140'),
        'in_reply_to_status_id' => array('type' => 'integer', 'length' => '11'),
        'in_reply_to_user_id' => array('type' => 'integer', 'length' => '11'),
        'in_reply_to_screen_name' => array('type' => 'string', 'length' => '255'),
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
        'in_reply_to_status_id' => array(
            'numeric' => array(
                'rule' => 'numeric',
                'message' => 'The ID of the status you are replying to should be numeric',
                'required' => false,
                'allowEmpty' => true,
            ),
        ),
        'in_reply_to_user_id' => array(
            'numeric' => array(
                'rule' => 'numeric',
                'message' => 'The ID of the user you are replying to should be numeric',
                'required' => false,
                'allowEmpty' => true,
            ),
        ),
    );
    /**
     * Custom find types available on this model
     *
     * @var array
     */
    public $_findMethods = array(
        'publicTimeline' => true,
        'homeTimeline' => true,
        'friendsTimeline' => true,
        'userTimeline' => true,
        'mentions' => true,
        'retweetedByMe' => true,
        'retweetedToMe' => true,
        'show' => true,
        'retweetsOfMe' => true,
        'retweets' => true,
        'retweetedBy' => true,
        'retweetedByIds' => true,
    );
    /**
     * The custom find types that require authentication
     *
     * @var array
     */
    public $findMethodsRequiringAuth = array(
        'homeTimeline',
        'friendsTimeline',
        'userTimeline',
        'mentions',
        'retweetedByMe',
        'retweetedToMe',
        'retweetsOfMe',
        'retweetedBy',
        'retweetedByIds',
    );
    /**
     * The options allowed by each of the custom find types
     * 
     * @var array
     */
    public $allowedFindOptions = array(
        'publicTimeline' => array('trim_user', 'include_entities'),
        'homeTimeline' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_entities'),
        'friendsTimeline' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_rts', 'include_entities'),
        'userTimeline' => array('user_id', 'screen_name', 'since_id', 'max_id', 'count', 'page', 'trim_user', 'include_rts', 'include_entities'),
        'mentions' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_rts', 'include_entities'),
        'retweetedByMe' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_entities'),
        'retweetedToMe' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_entities'),
        'retweetsOfMe' => array('since_id', 'max_id', 'count', 'page', 'trim_user', 'include_entities'),
        'show' => array('id', 'trim_user', 'include_entities'),
        'retweets' => array('id', 'count', 'trim_user', 'include_entities'),
        'retweetedBy' => array('id', 'count', 'page', 'trim_user', 'include_entities'),
        'retweetedByIds' => array('id', 'count', 'page', 'trim_user', 'include_entities'),
    );

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
        if (!empty($options['limit']) && empty($options['count'])) {
            $options['count'] = $options['limit'];
        }
        if ((empty($options['page']) || empty($options['count']))
                && array_key_exists($type, $this->allowedFindOptions)
                && in_array('page', $this->allowedFindOptions[$type])
                && in_array('count', $this->allowedFindOptions[$type])) {
            $options['page'] = 1;
            $options['count'] = 200;
            $results = array();
            while (($page = $this->find($type, $options)) != false) {
                $results = array_merge($results, $page);
                $options['page']++;
            }
            return $results;
        }
        if (method_exists($this, '_find' . Inflector::camelize($type))) {
            return parent::find($type, $options);
        }
        $this->request['uri']['path'] = '1/statuses/' . Inflector::underscore($type);
        if (array_key_exists($type, $this->allowedFindOptions)) {
            $this->request['uri']['query'] = array_intersect_key($options, array_flip($this->allowedFindOptions[$type]));
        }
        if (in_array($type, $this->findMethodsRequiringAuth)) {
            $this->request['auth'] = true;
        }
        return parent::find('all', $options);
    }

    /**
     * show
     * -------------
     *
     *     TwitterStatus::find('show', $options)
     *
     * @param $state string 'before' or 'after'
     * @param $query array
     * @param $results array
     * @return mixed
     * @access protected
     * */
    protected function _findShow($state, $query = array(), $results = array()) {
        if ($state === 'before') {
            if (empty($query['id'])) {
                return false;
            }
            $this->request = array(
                'uri' => array('path' => '1/statuses/show/' . $query['id']),
            );
            unset($query['id']);
            $this->request['uri']['query'] = array_intersect_key($query, array_flip($this->allowedFindOptions['show']));
            return $query;
        } else {
            return $results;
        }
    }

    /**
     * retweets
     * -------------
     *
     *     TwitterStatus::find('retweets', $options)
     *
     * @param $state string 'before' or 'after'
     * @param $query array
     * @param $results array
     * @return mixed
     * @access protected
     * */
    protected function _findRetweets($state, $query = array(), $results = array()) {
        if ($state === 'before') {
            if (empty($query['id'])) {
                return false;
            }
            $this->request = array(
                'uri' => array('path' => '1/statuses/retweets/' . $query['id']),
            );
            unset($query['id']);
            $this->request['uri']['query'] = array_intersect_key($query, array_flip($this->allowedFindOptions['show']));
            return $query;
        } else {
            return $results;
        }
    }

    /**
     * Retweeted By
     * -------------
     *
     *     TwitterStatus::find('retweetedBy', $options)
     *
     * @param $state string 'before' or 'after'
     * @param $query array
     * @param $results array
     * @return mixed
     * @access protected
     * */
    protected function _findRetweetedBy($state, $query = array(), $results = array()) {
        if ($state === 'before') {
            if (empty($query['id'])) {
                return false;
            }
            $this->request = array(
                'uri' => array(
                    'path' => '1/statuses/' . $query['id'] . '/retweeted_by'
                ),
                'auth' => true,
            );
            unset($query['id']);
            if ($query['count'] > 100) {
                $query['count'] = 100;
            }
            $this->request['uri']['query'] = array_intersect_key($query, array_flip($this->allowedFindOptions['retweetedBy']));
            return $query;
        } else {
            return $results;
        }
    }

    /**
     * Retweeted By Ids
     * -------------
     *
     *     TwitterStatus::find('retweetedByIds', $options)
     *
     * @param $state string 'before' or 'after'
     * @param $query array
     * @param $results array
     * @return mixed
     * @access protected
     * */
    protected function _findRetweetedByIds($state, $query = array(), $results = array()) {
        if ($state === 'before') {
            if (empty($query['id'])) {
                return false;
            }
            $this->request = array(
                'uri' => array(
                    'path' => '1/statuses/' . $query['id'] . '/retweeted_by/ids'
                ),
                'auth' => true,
            );
            unset($query['id']);
            if ($query['count'] > 100) {
                $query['count'] = 100;
            }
            $this->request['uri']['query'] = array_intersect_key($query, array_flip($this->allowedFindOptions['retweetedBy']));
            return $query;
        } else {
            return $results;
        }
    }

    /**
     * Creates a tweet
     *
     * @param mixed $data
     * @param mixed $validate
     * @param mixed $fieldList
     * @return mixed
     */
    public function tweet($data = null, $validate = true, $fieldList = array()) {
        $this->request = array(
            'uri' => array(
                'path' => '1/statuses/update',
            ),
        );
        if (isset($data[$this->alias]['text'])) {
            $this->request['body'] = array(
                'status' => $data[$this->alias]['text'],
            );
        }
        return $this->save($data, $validate, $fieldList);
    }

    /**
     * Retweets a tweet
     *
     * @param integer $id Id of the tweet you want to retweet
     * @return mixed
     */
    public function retweet($id = null) {
        if (!$id) {
            return false;
        }
        if (!is_numeric($id)) {
            return false;
        }
        $this->request = array(
            'uri' => array(
                'path' => '1/statuses/retweet/' . $id,
            ),
        );
        $this->create();
        // Dummy data ensures Model::save() does in fact call DataSource::create()
        $data = array($this->alias => array('text' => 'dummy'));
        return $this->save($data);
    }

    /**
     * Called by tweet or retweet
     *
     * @param mixed $data
     * @param mixed $validate
     * @param mixed $fieldList
     * @return mixed
     */
    public function save($data = null, $validate = true, $fieldList = array()) {
        $this->request['auth'] = true;
        $result = parent::save($data, $validate, $fieldList);
        if ($result && !empty($this->response['id_str'])) {
            $this->setInsertID($this->response['id_str']);
        }
        return $result;
    }

    /**
     * Deletes a tweet
     *
     * @param integer $id Id of the tweet to be deleted
     * @param boolean $cascade
     * @return boolean
     */
    public function delete($id = null, $cascade = true) {
        $this->request = array(
            'uri' => array(
                'path' => '1/statuses/destroy/' . $id,
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
    function exists() {
        if ($this->getID() === false) {
            return false;
        }
        $_request = $this->request;
        $result = $this->find('show', array('id' => $this->getID()));
        $this->request = $_request;
        return!empty($result);
    }

}
