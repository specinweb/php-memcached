<?php

namespace Memcached;

use Memcached\Client;
use PHPUnit_Framework_TestCase;

class ClientTest extends PHPUnit_Framework_TestCase
{
    protected string $host = '127.0.0.1';

    protected Client $client;

    protected string $key;

    protected string $value;

    protected function setUp()
    {
        $this->key = md5(microtime(true));
        $this->value = sha1($this->key);

        $this->client = new Client(
            $this->host
        );
    }

    public function testSetAKeyValuePair()
    {
        $this->assertTrue($this->client->set($this->key, $this->value));
    }

    public function testGetAValueByKey()
    {
        $this->assertTrue($this->client->set($this->key, $this->value));
        $this->assertEquals(
            $this->value,
            $this->client->get($this->key)
        );

        $this->assertFalse($this->client->get(md5($this->key)));
    }

    public function testAddAKeyValuePair()
    {
        $this->assertTrue($this->client->add($this->key, $this->value));
        $this->assertEquals(
            $this->value,
            $this->client->get($this->key)
        );
        $this->assertFalse($this->client->add($this->key, $this->value));
    }

    public function testReplaceAnExistingValue()
    {
        srand(microtime(true));
        $value = md5(rand(1, 65535));

        $this->assertFalse($this->client->replace($this->key, $value));

        $this->assertTrue($this->client->set($this->key, $this->value));
        $this->assertTrue($this->client->replace($this->key, $value));

        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testAppendAValueToAnExistingOne()
    {
        srand(microtime(true));
        $value = md5(rand(1, 65535));

        $this->assertFalse($this->client->append($this->key, $value));

        $this->assertTrue($this->client->set($this->key, $this->value));
        $this->assertTrue($this->client->append($this->key, $value));

        $this->assertEquals(
            $this->value . $value,
            $this->client->get($this->key)
        );
    }

    public function testPrependAValueToAnExistingOne()
    {
        srand(microtime(true));
        $value = md5(rand(1, 65535));

        $this->assertFalse($this->client->prepend($this->key, $value));

        $this->assertTrue($this->client->set($this->key, $this->value));
        $this->assertTrue($this->client->prepend($this->key, $value));

        $this->assertEquals(
            $value . $this->value,
            $this->client->get($this->key)
        );
    }

    public function testCasSetAKeyValuePair()
    {
        srand(microtime(true));
        $value = rand(0, 65535);

        $this->assertFalse($this->client->cas($value, $this->key, $value));

        $this->assertTrue($this->client->set($this->key, $this->value));

        $this->assertEquals(
            'bar',
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeString()
    {
        $value = 'Hello World!';

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_string($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeFloat()
    {
        $value = 5.23;

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_float($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeInteger()
    {
        $value = 523;

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_int($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeArray()
    {
        $value = [
            5,
            23,
        ];

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_array($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeObject()
    {
        $value = new \stdClass();
        $value->{$this->key} = $this->value;

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_object($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testStoringPhpTypeNull()
    {
        $value = null;

        $this->assertTrue($this->client->set($this->key, $value));
        $this->assertTrue(is_null($this->client->get($this->key)));
        $this->assertEquals(
            $value,
            $this->client->get($this->key)
        );
    }

    public function testConnectToAMemcachedDaemon()
    {
        $this->assertTrue(
            is_resource(
                $this->client->connect($this->host, Client::DEFAULT_PORT)
            )
        );

        $this->client->connect('1.2.3.4', '11211', 1);
    }
}
