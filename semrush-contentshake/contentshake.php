<?php

/**
 * Plugin Name: Semrush ContentShake
 * Description: Semrush ContentShake WordPress plugin
 * Version: 1.1.30
 * Author: Semrush
 * Author URI: https://www.semrush.com/
 * License: GPLv3
 *
 * Text Domain: contentshake
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || die( "Can't access directly" );

const CONTENTSHAKE_API_KEY_OPTION           = 'semrush_contentshake_api_key';
const CONTENTSHAKE_API_KEY_ACCEPTING_OPTION = 'semrush_contentshake_api_key_accepting';
const CONTENTSHAKE_AUTH_URL                 = 'https://www.semrush.com/app/contentshake/integration/wp';
const CONTENTSHAKE_NAMESPACE                = 'contentshake/v1';

/**
 * Plugin class.
 */
class WP_Semrush_ContentShake {

    /**
     * Instance of plugin.
     */
    private static $instance;

    /**
     * Constructs singleton.
     */
    public static function getInstance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Setup action & filter hooks.
     */
    public function __construct() {
        add_option( CONTENTSHAKE_API_KEY_OPTION, array() );

        add_action( 'plugins_loaded', array( $this, 'load_localization' ) );
        
        add_filter( 'jwt_auth_default_whitelist', array( $this, 'jwt_auth_whitelist' ) );
        add_filter( 'api_bearer_auth_unauthenticated_urls', array( $this, 'api_bearer_auth_whitelist' ) );

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'rest_api_init', array( $this, 'register_api_endpoints' ) );
    }

    /**
     * Load localization
     */
    public function load_localization() {
        load_plugin_textdomain( 'contentshake', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Register Plugin Rest API endpoints
     */
    public function register_api_endpoints() {
        /**
         * Add field ContentShake article id to post
         */
        register_rest_field('post', 'contentshake_article_id', array(
            'get_callback' => array( $this, 'get_contentshake_article_id' ),
            'update_callback' => array( $this, 'update_contentshake_article_id' ),
            'schema' => array(
                'title' => 'Article Id',
                'description' => 'Semrush ContentShake Article Id',
                'type' => 'string',
                'format' => 'uuid'
            )
        ) );

        /**
         * Posts
         */
        $posts_controller = new WP_REST_Posts_Controller( 'post' );

        register_rest_route( CONTENTSHAKE_NAMESPACE, '/posts', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_or_update_post' ),
                'permission_callback' => array( $this, 'check_token' ),
                'args'                => $posts_controller->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE )
            ),
            'schema' => array( $posts_controller, 'get_public_item_schema' )
        ) );

        /**
         * Accepted connection
         */
        register_rest_route( CONTENTSHAKE_NAMESPACE, '/accepted', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'accepted_token' ),
                'permission_callback' => array( $this, 'check_accepted_token' ),
                'args'                => array()
            )
        ) );

        /**
         * Decline connection
         */
        register_rest_route( CONTENTSHAKE_NAMESPACE, '/decline', array(
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_token' ),
                'permission_callback' => array( $this, 'check_token' ),
                'args'                => array()
            )
        ) );
    }

    /**
     * Create or update post
     */
    public function create_or_update_post( WP_REST_Request $request ) {
        $posts_controller = new WP_REST_Posts_Controller( 'post' );
        $body = json_decode( $request->get_body(), true );

        if ( array_key_exists( 'contentshake_article_id', $body ) ) {
            $contentshake_id = $body['contentshake_article_id'];
            $query = new WP_Query( array(
                'numberposts' => 1,
                'post_type'   => 'post',
                'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
                'meta_key'    => 'contentshake_article_id',
                'meta_value'  => $contentshake_id
            ) );

            if ( $query->have_posts() ) {
                $post = $query->next_post();
                $body['id'] = $post->ID;
                $request->set_body( wp_json_encode( $body ) );
                return $posts_controller->update_item( $request );
            } 
        } 
        return $posts_controller->create_item( $request );
    }

    /**
     * Accepted token
     */
    public function accepted_token( WP_REST_Request $request ) {
        $token = isset( $_SERVER['HTTP_TOKEN'] ) ? sanitize_text_field( $_SERVER['HTTP_TOKEN'] ) : '';

        $tokens = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
        $user_email = array_search( $token, $tokens );
        unset( $tokens[$user_email] );
        update_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, $tokens );
        
        $tokens[$user_email] = $token;
        update_option( CONTENTSHAKE_API_KEY_OPTION, $tokens );
    }

    /**
     * Decline token
     */
    public function delete_token( WP_REST_Request $request ) {
        $token = isset( $_SERVER['HTTP_TOKEN'] ) ? sanitize_text_field( $_SERVER['HTTP_TOKEN'] ) : '';

        $tokens = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        $user_email = array_search( $token, $tokens );
        unset( $tokens[$user_email] );
        update_option( CONTENTSHAKE_API_KEY_OPTION, $tokens );
    }

    /**
     * Check token and set current user
     */
    public function check_token( WP_REST_Request $request ) {
        $token = isset( $_SERVER['HTTP_TOKEN'] ) ? sanitize_text_field( $_SERVER['HTTP_TOKEN'] ) : '';

        wp_set_current_user( $this->get_user_by_token( $token ) );

        $expecteds = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        return in_array( $token, $expecteds );
    }

    /**
     * Check token and set current user
     */
    public function check_accepted_token( WP_REST_Request $request ) {
        $token = isset( $_SERVER['HTTP_TOKEN'] ) ? sanitize_text_field( $_SERVER['HTTP_TOKEN'] ) : '';

        wp_set_current_user( $this->get_user_by_token( $token ) );

        $expecteds = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
        return in_array( $token, $expecteds );
    }


    /**
     * Get ContentShake Article Id
     */
    public function get_contentshake_article_id($post, $field_name, $request) {
        return get_post_meta( $post['id'], $field_name, true );
    }

    /**
     * Update ContentShake Article Id
     */
    public function update_contentshake_article_id($value, $post, $field_name) {
        if ( ! $value || !is_string( $value )) {
            return;
        }
        return update_post_meta( $post->ID, $field_name, trim($value) );
    }

    /**
     * Get user id by token
     */
    public function get_user_by_token( $token ) {
        $expecteds = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        $user_email = array_search( $token, $expecteds );

        if ( $user_email ) {
            $user = get_user_by( 'email', $user_email );
            return $user->ID;
        }

        return 0;
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page( 'Semrush ContentShake Settings', 'ContentShake', 'manage_options', 'contentshake', array( $this, 'create_settings_page' ) );
    }

    /**
     * Settings page registration
     */
    public function create_settings_page() {
        include plugin_dir_path( __FILE__ ) . '/pages/settings.php';
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'contentshake', CONTENTSHAKE_API_KEY_OPTION );
    }

    /**
     * Filter ContentShake requests for `jwt-auth` plugin.
     */
    public function jwt_auth_whitelist( $default_whitelist ) {
        $rest_api_slug = $this->get_api_slug() . '/' . CONTENTSHAKE_NAMESPACE;
        array_push($default_whitelist, $rest_api_slug . '/');
        return $default_whitelist;
    }

    /**
     * Filter ContentShake requests for `api-bearer-auth` plugin.
     */
    public function api_bearer_auth_whitelist( $default_whitelist ) {
        $rest_api_slug = $this->get_api_slug() . '/' . CONTENTSHAKE_NAMESPACE;
        array_push($default_whitelist, $rest_api_slug . '/posts/?');
        array_push($default_whitelist, $rest_api_slug . '/accepted/?');
        array_push($default_whitelist, $rest_api_slug . '/decline/?');
        return $default_whitelist;
    }

    /**
     * Get Rest API prefix.
     */
    private function get_api_slug() {
        $rest_api_slug = get_option( 'permalink_structure' ) ? rest_get_url_prefix() : '?rest_route=/';
        $rest_api_slug = home_url( '/' . $rest_api_slug, 'relative' );
        return $rest_api_slug;
    }

}

WP_Semrush_ContentShake::getInstance();
