<?php

use MDurys\SkapiecAPI\Client as SkapiecClient;

class ClientTest extends PHPUnit_Framework_TestCase
{
    private $api;

    public function setUp()
    {
        $this->api = new SkapiecClient('test', 'test');
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Tried to call unknown method MDurys\SkapiecAPI\Client::beta_badMethod
     */
    public function testBadMethod()
    {
        $this->api->beta_badMethod();
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage MDurys\SkapiecAPI\Client::beta_getProductPhoto requires 2 argument(s)
     */
    public function testMethodTooFewArguments()
    {
        $this->api->beta_getProductPhoto(123456);
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage MDurys\SkapiecAPI\Client::beta_getDealerInfo requires 1 argument(s)
     */
    public function testMethodTooManyArguments()
    {
        $this->api->beta_getDealerInfo(123456, 'extra argument');
    }

    /**
     * @expectedException MDurys\SkapiecAPI\Exception
     * @expectedExceptionCode 401
     * @expectedExceptionMessage Niepoprawna nazwa uzytkownika lub haslo w api.skapiec.pl
     */
    public function testAccessDenied()
    {
        $this->api->meta_whoAmI();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage test is not a valid output format
     */
    public function testUnknownOutputFormat()
    {
        $this->api->setOutputFormat('test');
    }
}
