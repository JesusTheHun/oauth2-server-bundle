<?php

namespace OAuth2\ServerBundle\Tests;

use OAuth2\Request;
use OAuth2\Response;
use OAuth2\ServerBundle\Tests\ContainerLoader;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testOpenIdConfig()
    {
        $openIdConfig = <<<EOF
<?xml version="1.0"?>
<container xmlns="http://symfony.com/schema/dic/services" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
    <parameters>
        <parameter key="oauth2.server.config" type="collection">
            <parameter key="use_openid_connect">true</parameter>
            <parameter key="issuer">oauth2-server-bundle</parameter>
        </parameter>
    </parameters>
</container>
EOF;
        file_put_contents($tmpFile = tempnam(sys_get_temp_dir(), 'openid-config'), $openIdConfig);
        $container = ContainerLoader::buildTestContainer(array(
            __DIR__.'/../vendor/symfony/symfony/src/Symfony/Bundle/SecurityBundle/Resources/config/security.xml',
            $tmpFile
        ));

        $config = $container->getParameter('oauth2.server.config');
        $server = $container->get('oauth2.server');

        $this->assertTrue($server->getConfig('use_openid_connect'));
        $this->assertNotNull($server->getStorage('public_key'));

        $clientId = 'test-client-' . rand();

        $server->getStorage('public_key')->keys['public_key'] = file_get_contents(__DIR__.'/../vendor/bshaffer/oauth2-server-php/test/config/keys/id_rsa.pub');
        $server->getStorage('public_key')->keys['private_key'] = file_get_contents(__DIR__.'/../vendor/bshaffer/oauth2-server-php/test/config/keys/id_rsa');
        $server->getStorage('client_credentials')->setClientDetails($clientId, 'test-client-secret');
        $request = new Request(array(
            'client_id'     => $clientId,
            'redirect_uri'  => 'http://brentertainment.com',
            'response_type' => 'code',
            'scope'         => 'openid',
            'state'         => 'xyz',
        ));

        $response = new Response();
        $server->handleAuthorizeRequest($request, $response, true);
        $parts = parse_url($response->getHttpHeader('Location'));
        parse_str($parts['query'], $query);
        $code = $server->getStorage('authorization_code')->getAuthorizationCode($query['code']);

        $this->assertArrayHasKey('id_token', $code);
    }
}