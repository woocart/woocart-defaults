<?php

namespace Niteo\WooCart\Defaults {

    use Lcobucci\JWT\Parser;
    use Lcobucci\JWT\ValidationData;
    use Lcobucci\JWT\Signer\Hmac\Sha256;


	/**
	 * Class AutoLogin
	 *
	 * @package Niteo\WooCart\Defaults
	 */
	class AutoLogin {

        /**
         * AutoLogin constructor.
         */
        function __construct() {
            if ( is_blog_installed() ) { // only run login functions on installed blog
                add_action( 'init', array(&$this, 'test_for_auto_login') );
                add_action( 'init', array(&$this, 'fix_login_auth'), ~PHP_INT_MAX );
            }
        }

        /**
         * Handle redirection to www without loosing auth parameter.
         */
        function fix_login_auth(): void {
            if (
                isset( $_GET['auth'] ) && // contains auth
                ( strpos( get_home_url(), 'www.' ) !== false ) && // site uses www subdomain
                !( strpos( $_SERVER['SERVER_NAME'], 'www.' ) !== false ) // current hostname is not www
            ) {
                $url = get_home_url() . "/wp-login.php?auth=" . $_GET['auth'];
                wp_redirect( $url . '&rewww=' . microtime() );
                exit();
            }
        }

        /**
         * Auto login as administrator without knowing username and password.
         */
        function auto_login(): void {
            $users = get_users( array('role' => 'administrator', 'orderby' => 'ID') );
            if (count($users) > 0) {
                $user = $users[0];
                wp_set_auth_cookie( $user->ID, true, '' );
                do_action( 'wp_login', $user->get('user_login'), $user );
            }
        }

        /**
         * Validate jwt token.
         *
         * @param string $auth jwt token.
         * @param string $secret shared secret to verify jwt token.
         */
        public function validate_jwt_token( $auth, $secret ): bool {
            $data = new ValidationData();
            $signer = new Sha256();
            try {
                $token = (new Parser())->parse((string) $auth); // Parses from a string
            } catch (\Exception $e) {
                return false;
            }
            return $token->validate($data) && $token->verify($signer, $secret);
        }

        /**
         * Auto login to WP if auth token is valid.
         */
        function test_for_auto_login(): void {
            if ( isset($_GET['auth']) ) {
                if ( is_user_logged_in() ) {
                    wp_redirect( get_admin_url() . '?reloggedin=' . microtime() ); //always redirect to public page
                    exit;
                } else {
                    $auth = $_GET['auth'];
                    $secret = 'secret'; // TODO: update with loginSharedSecret
                    if ( $this->validate_jwt_token( $auth, $secret ) ) { //used by dashboard login
                        $this->auto_login();
                        wp_redirect( get_admin_url() . '?limited=' . microtime() ); //always redirect to admin page
                        exit;
                    } else {
                        return;
                    }
                }
            }
        }
    }
}
