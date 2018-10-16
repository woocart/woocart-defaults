<?php

use Niteo\WooCart\Defaults\AutoLogin;
use PHPUnit\Framework\TestCase;

class AutoLoginTest extends TestCase
{
    function setUp()
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction(
            'is_blog_installed', array(
                'return'    => true
            )
        );
    }

    function tearDown()
    {
        $this->addToAssertionCount(
            \Mockery::getContainer()->mockery_getExpectationCount()
        );
        \WP_Mock::tearDown();
        \Mockery::close();
    }


    /**
     * @covers \Niteo\WooCart\Defaults\AutoLogin::__construct
     */
    public function testConstructor()
    {
        $login = new AutoLogin();
        \WP_Mock::expectActionAdded( 'init', [ $login, 'test_for_auto_login' ] );
        \WP_Mock::expectActionAdded( 'init', [ $login, 'fix_login_auth' ], ~PHP_INT_MAX );

        $login->__construct();

    }

    /**
     * @covers \Niteo\WooCart\Defaults\AutoLogin::__construct
     * @covers \Niteo\WooCart\Defaults\AutoLogin::fix_login_auth
     */
    public function testFix_login_auth()
    {
        \WP_Mock::userFunction(
            'get_home_url', array(
                'return'    => 'www.store.com',
                'times'     => 2,
            )
        );
        \WP_Mock::userFunction(
            'wp_redirect', array(
                'times' => 1,
            )
        );
        $_GET['auth'] = 'foo_jwt_auth_token';
        $_SERVER['SERVER_NAME'] = 'store.com';
        $login = new AutoLogin();
        $login->fix_login_auth();
        // unset($_SERVER['SERVER_NAME']);
    }

    /**
     * @covers \Niteo\WooCart\Defaults\AutoLogin::__construct
     * @covers \Niteo\WooCart\Defaults\AutoLogin::test_for_auto_login
     */
    public function testTest_for_auto_login()
    {
        \WP_Mock::userFunction(
            'is_user_logged_in', array(
                'return'  => [ true, false, ],
                'times'   => 2,
            )
        );
        \WP_Mock::userFunction(
            'wp_redirect', array(
                'times'   => 2,
            )
        );
        \WP_Mock::userFunction(
            'get_admin_url', array(
                'return'  => 'store.com/wp-admin',
                'times'   => 2,
            )
        );

        $mock = \Mockery::mock('Niteo\WooCart\Defaults\AutoLogin[auto_login,validate_jwt_token]');
        $mock->shouldReceive( 'validate_jwt_token' )
            ->with( 'foo_jwt_auth_token', 'secret' )
            ->once()
            ->andReturn(true);
        $mock->shouldReceive( 'auto_login' )->once();

        $_GET['auth'] = 'foo_jwt_auth_token';

        $login = new AutoLogin();
        $login->test_for_auto_login();
    }

    /**
     * @covers \Niteo\WooCart\Defaults\AutoLogin::__construct
     * @covers \Niteo\WooCart\Defaults\AutoLogin::auto_login
     */
    public function testAuto_login()
    {
        $user = \Mockery::mock();
        $user->ID = 1;
        $user->shouldReceive( 'get' )
            ->with( 'user_login' )
            ->once()
            ->andReturn('user');
        \WP_Mock::userFunction(
            'get_users', array(
                'args'    => [ array('role' => 'administrator', 'orderby' => 'ID') ],
                'return'  => [ $user, ],
                'times'   => 1,
            )
        );
        \WP_Mock::userFunction(
            'wp_set_auth_cookie', array(
                'args'    => [ 1, true, '' ],
                'times'   => 1,
            )
        );
        \WP_Mock::expectAction( 'wp_login', 'user', $user );

        $login = new AutoLogin();
        $login->auto_login();
    }

    /**
     * @covers \Niteo\WooCart\Defaults\AutoLogin::__construct
     * @covers \Niteo\WooCart\Defaults\AutoLogin::validate_jwt_token
     */
    public function testValidate_jwt_token()
    {
        $login = new AutoLogin();

        // valid token without time limit
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE1MTYyMzkwMjJ9.ygbbkdw2ZRJSTyNSL5o8fKNLngAIQTkGsDCM8g6sGrg';
        $result = $login->validate_jwt_token($token, 'secret');
        $this->assertTrue($result);
        // valid token with expired time limit
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1MTYyMzkwMjJ9.7BbIenT4-HobiMHaMUQdNcJ6lD_QQkKnImP9IprJFvU';
        $result = $login->validate_jwt_token($token, 'secret');
        $this->assertFalse($result);
        // valid token with wrong secret
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE1MTYyMzkwMjJ9.UTLxY2zcznIFA42CIYV4iVWLEQhIrJyQ8I5eyZ_VpT8';
        $result = $login->validate_jwt_token($token, 'secret');
        $this->assertFalse($result);
        // invalid token
        $token = '2.2.2';
        $result = $login->validate_jwt_token($token, 'secret');
        $this->assertFalse($result);
        // invalid token
        $token = '2';
        $result = $login->validate_jwt_token($token, 'secret');
        $this->assertFalse($result);
    }
}
