<?php

/**
 * test TwimAppModel
 *
 * PHP versions 5
 *
 * Copyright 2011, nojimage (http://php-tips.com/)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @version   1.0
 * @author    nojimage <nojimage at gmail.com>
 * @copyright 2011 nojimage (http://php-tips.com/)
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link    　http://php-tips.com/
 * @since   　File available since Release 1.0
 *
 */
App::import('Model', array('Twim.TwimAppModel'));
App::import('Datasource', array('Twim.TwimSource'));

class TestTwimAppModel extends TwimAppModel {

    public $alias = 'TwimAppModel';
    public $useDbConfig = 'test_twitter_app';

}

class TwimTestOauth extends TestTwimAppModel {

    public $useDbConfig = 'test_twitter2';

}

class TestTwimAppModelTwimSource extends TwimSource {
    
}

ConnectionManager::create('test_twitter_app',
                array(
                    'datasource' => 'Twim.TwimSource',
                    'oauth_consumer_key' => 'cvEPr1xe1dxqZZd1UaifFA',
                    'oauth_consumer_secret' => 'gOBMTs7Rw4Z3p5EhzqBey8ousRTwNDvreJskN8Z60',
        ));

ConnectionManager::create('test_twitter2',
                array(
                    'datasource' => 'TestTwimAppModelTwimSource',
                    'oauth_consumer_key' => 'testConsumerKey',
                    'oauth_consumer_secret' => 'testConsumerSecret',
        ));

/**
 *
 * @property TwimAppModel $Twim
 */
class TwimAppModelTestCase extends CakeTestCase {

    function startTest($method) {
        $this->Twim = ClassRegistry::init('TestTwimAppModel');
    }

    function endTest($method) {
        unset($this->Twim);
        ClassRegistry::flush();
    }

    function test_construct() {
        $this->assertIdentical($this->Twim->getDataSource()->config['oauth_consumer_key'], 'cvEPr1xe1dxqZZd1UaifFA');
    }

    function test_construct_with_ds() {
        $this->Twim = ClassRegistry::init(array('class' => 'TestTwimAppModel', 'ds' => 'test_twitter2'));
        $this->assertIsA($this->Twim->getDataSource(), 'TestTwimAppModelTwimSource');
        $this->assertIdentical($this->Twim->getDataSource()->config['oauth_consumer_key'], 'testConsumerKey');
    }

    function test_getDataSource() {
        $this->assertIsA($this->Twim->getDataSource(), 'TwimSource');
        $this->assertIdentical($this->Twim->getDataSource()->config['oauth_consumer_key'], 'cvEPr1xe1dxqZZd1UaifFA');
    }

    function test_setDataSource() {
        $this->assertIdentical($this->Twim->getDataSource()->config['oauth_consumer_key'], 'cvEPr1xe1dxqZZd1UaifFA');
        $this->assertIdentical($this->Twim->setDataSource('test_twitter2')->getDataSource()->config['oauth_consumer_key'], 'testConsumerKey');
    }

    function test_loadTwimModel() {
        $this->assertIsA($this->Twim->TestOauth, 'TwimTestOauth');
        $this->assertIdentical($this->Twim->TestOauth->getDataSource()->config['oauth_consumer_key'], 'testConsumerKey');
    }

}