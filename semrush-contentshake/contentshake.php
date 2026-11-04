<?php

/**
 * Plugin Name: Semrush Content Toolkit
 * Description: Semrush Content Toolkit WordPress plugin
 * Version: 1.2.0
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
const CONTENTSHAKE_NAMESPACE                = 'contentshake/v1';
const CONTENTSHAKE_TOKEN_INPUT_ID           = 'contentshake_token';
const CONTENTSHAKE_COPY_BUTTON_ID           = 'contentshake_copy';
const CONTENTSHAKE_NAMESPACE_V2             = 'contentshake/v2';

const TAGS_FIELD_NAME = 'semrush_tags';
// Each tag is a separate post meta, so posts can be matched by any subset of tags.
// The leading underscore hides them from the Custom Fields box.
const TAG_META_PREFIX = '_semrush_tag_';
// Tag stored in the v1 meta as is, so v2 finds posts published through v1 and vice versa.
const ARTICLE_ID_TAG = 'contentshake_article_id';

/**
 * Posts controller of the plugin's /posts route.
 *
 * Unlike /wp/v2/posts, the route does not depend on the 'post' type being shown in REST
 * (show_in_rest), so an update is not refused for that. All other rights checks are core's.
 */
class WP_Semrush_ContentShake_Posts_Controller extends WP_REST_Posts_Controller {
    protected function check_is_post_type_allowed( $post_type ) {
        return true;
    }
}

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
        add_filter( 'rest_authentication_errors', array( $this, 'allow_plugin_routes' ), 11 );

        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

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
        register_rest_field('post', ARTICLE_ID_TAG, array(
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
         * Add field Semrush tags to post
         */
        register_rest_field('post', TAGS_FIELD_NAME, array(
            'get_callback' => array( $this, 'get_semrush_tags' ),
            'update_callback' => array( $this, 'update_semrush_tags' ),
            'schema' => array(
                'title' => 'Tags to mark the post',
                'description' => 'Set of post’s properties for Semrush tools',
                'type' => 'object',
                // Values are strings or numbers. Not declared as a type union, since WordPress
                // would then sanitize numeric strings into numbers ("007" -> 7).
                'arg_options' => array(
                    'validate_callback' => array( $this, 'validate_semrush_tags_param' ),
                    'sanitize_callback' => array( $this, 'normalize_semrush_tags' )
                )
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
         * Posts matched by Semrush tags
         */
        register_rest_route( CONTENTSHAKE_NAMESPACE_V2, '/posts', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_or_update_post_v2' ),
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
                // Only the exact token is removed, so unknown or already declined tokens are a no-op.
                'permission_callback' => '__return_true',
                'args'                => array()
            )
        ) );

        /**
         * Plugin version, so Semrush can check compatibility before connecting
         */
        register_rest_route( CONTENTSHAKE_NAMESPACE, '/version', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_version' ),
                // The version is public anyway (readme.txt on wordpress.org).
                'permission_callback' => '__return_true',
                'args'                => array()
            ),
            'schema' => array( $this, 'get_version_schema' )
        ) );
    }

    /**
     * Plugin version from the plugin header
     */
    public function get_version( WP_REST_Request $request ) {
        $plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
        return rest_ensure_response( array( 'version' => $plugin_data['Version'] ) );
    }

    /**
     * Schema of the version response
     */
    public function get_version_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'contentshake-version',
            'type'       => 'object',
            'properties' => array(
                'version' => array(
                    'description' => 'Semrush Content Toolkit plugin version',
                    'type'        => 'string',
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * Create or update post
     *
     * The route's permission_callback only checks the token, so the post rights of its
     * user are checked here, the same way as on /wp/v2/posts.
     */
    public function create_or_update_post( WP_REST_Request $request ) {
        $posts_controller = new WP_Semrush_ContentShake_Posts_Controller( 'post' );
        $body = $request->get_json_params();

        if ( ! is_array( $body ) ) {
            return new WP_Error( 'contentshake_invalid_body', 'The request body must be JSON.', array( 'status' => 400 ) );
        }

        // A null id skips the schema validation and would match any post that has an id.
        if ( ! empty( $body['contentshake_article_id'] ) ) {
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

                $allowed = $posts_controller->update_item_permissions_check( $request );
                return is_wp_error( $allowed ) ? $allowed : $posts_controller->update_item( $request );
            }
        }

        $allowed = $posts_controller->create_item_permissions_check( $request );
        return is_wp_error( $allowed ) ? $allowed : $posts_controller->create_item( $request );
    }

    /**
     * Create or update post matched by Semrush tags.
     *
     * A post matches when it has all the tags from the request with equal values,
     * e.g. { "content_id": "some_id", "project_id": "other_id" }. Tags the request
     * doesn't mention are ignored for matching. Post rights are checked as in v1.
     */
    public function create_or_update_post_v2( WP_REST_Request $request ) {
        $posts_controller = new WP_Semrush_ContentShake_Posts_Controller( 'post' );
        $body = $request->get_json_params();

        if ( ! is_array( $body ) ) {
            return new WP_Error( 'contentshake_invalid_body', 'The request body must be JSON.', array( 'status' => 400 ) );
        }

        if ( ! empty( $body[TAGS_FIELD_NAME] ) ) {
            // JSON params come already normalized by the route's sanitize_callback.
            $meta_query = array( 'relation' => 'AND' );
            foreach ( $body[TAGS_FIELD_NAME] as $key => $value ) {
                $meta_query[] = array(
                    'key'     => $this->get_tag_meta_key( $key ),
                    'value'   => $value,
                    'compare' => '='
                );
            }

            $query = new WP_Query( array(
                'posts_per_page' => 1,
                'no_found_rows'  => true,
                'post_type'      => 'post',
                'post_status'    => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
                'meta_query'     => $meta_query
            ) );

            if ( $query->have_posts() ) {
                $post = $query->next_post();
                $body['id'] = $post->ID;
                $request->set_body( wp_json_encode( $body ) );

                $allowed = $posts_controller->update_item_permissions_check( $request );
                return is_wp_error( $allowed ) ? $allowed : $posts_controller->update_item( $request );
            }
        }

        $allowed = $posts_controller->create_item_permissions_check( $request );
        return is_wp_error( $allowed ) ? $allowed : $posts_controller->create_item( $request );
    }

    /**
     * Accepted token
     *
     * Responds with the username of the token's user, set as current by check_accepted_token().
     */
    public function accepted_token( WP_REST_Request $request ) {
        $token    = $this->get_request_token( $request );
        $response = rest_ensure_response( array( 'username' => wp_get_current_user()->user_login ) );

        $accepting = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
        $user_email = array_search( $token, $accepting, true );

        // Token is already accepted — repeated call is a no-op.
        if ( false === $user_email ) {
            return $response;
        }

        $tokens = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        $tokens[$user_email] = $token;

        // update_option() also returns false when the value is unchanged, hence the re-read.
        if ( ! update_option( CONTENTSHAKE_API_KEY_OPTION, $tokens ) && get_option( CONTENTSHAKE_API_KEY_OPTION ) !== $tokens ) {
            return new WP_Error( 'contentshake_token_not_saved', 'The token could not be saved.', array( 'status' => 500 ) );
        }

        // Drop the pending entry only after activation, so a failed request can be retried.
        unset( $accepting[$user_email] );
        update_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, $accepting );

        return $response;
    }

    /**
     * Decline token
     */
    public function delete_token( WP_REST_Request $request ) {
        $token = $this->get_request_token( $request );

        $tokens    = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        $accepting = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );

        $user_email = array_search( $token, $tokens, true );
        if ( false !== $user_email ) {
            unset( $tokens[$user_email] );
        } else {
            // A pending token only: the same user's active token, if any, stays.
            $user_email = array_search( $token, $accepting, true );
        }

        // Token is unknown or already declined — repeated call is a no-op.
        if ( false === $user_email ) {
            return;
        }

        // Drop the user's pending entry as well: the declined token itself, or a stale one
        // left next to a declined active token (so the settings page issues a fresh token).
        unset( $accepting[$user_email] );

        // A failed save leaves the token usable, so it is reported instead of a 200.
        // update_option() also returns false when the value is unchanged, hence the re-reads.
        if ( ( ! update_option( CONTENTSHAKE_API_KEY_OPTION, $tokens ) && get_option( CONTENTSHAKE_API_KEY_OPTION ) !== $tokens )
            || ( ! update_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, $accepting ) && get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION ) !== $accepting ) ) {
            return new WP_Error( 'contentshake_token_not_deleted', 'The token could not be deleted.', array( 'status' => 500 ) );
        }
    }

    /**
     * Check token and set current user
     *
     * A token whose WordPress user is gone (deleted or renamed) is rejected.
     */
    public function check_token( WP_REST_Request $request ) {
        $user_id = $this->get_user_by_token( $this->get_request_token( $request ) );

        if ( ! $user_id ) {
            return false;
        }

        wp_set_current_user( $user_id );
        return true;
    }

    /**
     * Check pending or already accepted token and set current user
     *
     * As in check_token(), a token whose WordPress user is gone (deleted or renamed) is rejected.
     */
    public function check_accepted_token( WP_REST_Request $request ) {
        $token = $this->get_request_token( $request );

        $accepting = get_option( CONTENTSHAKE_API_KEY_ACCEPTING_OPTION, array() );
        $accepted  = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );

        $user_email = array_search( $token, $accepting, true );
        if ( false === $user_email ) {
            $user_email = array_search( $token, $accepted, true );
        }

        $user = $user_email ? get_user_by( 'email', $user_email ) : false;
        if ( ! $user ) {
            return false;
        }

        wp_set_current_user( $user->ID );
        return true;
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
     * Plugin token from the `Token` request header
     */
    private function get_request_token( WP_REST_Request $request ) {
        return sanitize_text_field( (string) $request->get_header( 'token' ) );
    }

    /**
     * Get Semrush tags
     */
    public function get_semrush_tags($post, $field_name, $request) {
        $tags = array();
        foreach ( get_post_meta( $post['id'] ) as $meta_key => $values ) {
            if ( 0 === strpos( $meta_key, TAG_META_PREFIX ) ) {
                $tags[ substr( $meta_key, strlen( TAG_META_PREFIX ) ) ] = $values[0];
            } elseif ( ARTICLE_ID_TAG === $meta_key && '' !== $values[0] ) {
                $tags[ ARTICLE_ID_TAG ] = $values[0];
            }
        }
        // Empty object rather than [] in JSON.
        return (object) $tags;
    }

    /**
     * Update Semrush tags. Given tags are set, other tags of the post stay as they are.
     */
    public function update_semrush_tags($value, $post, $field_name) {
        $tags = $this->normalize_semrush_tags( $value );
        if ( is_wp_error( $tags ) ) {
            return $tags;
        }
        foreach ( $tags as $key => $tag_value ) {
            update_post_meta( $post->ID, $this->get_tag_meta_key( $key ), wp_slash( $tag_value ) );
        }
        return true;
    }

    /**
     * Validate Semrush tags before the post is saved
     */
    public function validate_semrush_tags_param( $value, $request = null, $param = '' ) {
        $tags = $this->normalize_semrush_tags( $value );
        return is_wp_error( $tags ) ? $tags : true;
    }

    /**
     * Normalize Semrush tags: an object of string or number values with keys made of
     * [A-Za-z0-9_-]. Values are returned as trimmed strings, the way post meta stores them.
     */
    public function normalize_semrush_tags( $tags, $request = null, $param = '' ) {
        // A JSON list decodes like an object with keys 0..n, so such an object is rejected too.
        if ( ! is_array( $tags ) || ( $tags && array_is_list( $tags ) ) ) {
            return new WP_Error( 'rest_invalid_param', 'semrush_tags must be an object.', array( 'status' => 400 ) );
        }
        $valid = array();
        foreach ( $tags as $key => $value ) {
            // json_decode() turns digit-only keys into integers.
            if ( ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $key ) ) {
                return new WP_Error( 'rest_invalid_param', sprintf( 'Invalid semrush_tags key: %s.', $key ), array( 'status' => 400 ) );
            }
            if ( is_int( $value ) || is_float( $value ) ) {
                $value = (string) $value;
            }
            if ( ! is_string( $value ) || '' === trim( $value ) ) {
                return new WP_Error( 'rest_invalid_param', sprintf( 'semrush_tags.%s must be a non-empty string or a number.', $key ), array( 'status' => 400 ) );
            }
            $valid[ $key ] = trim( $value );
        }
        return $valid;
    }

    /**
     * Post meta key of a Semrush tag
     */
    private function get_tag_meta_key( $key ) {
        return ARTICLE_ID_TAG === $key ? ARTICLE_ID_TAG : TAG_META_PREFIX . $key;
    }

    /**
     * Get user id by token
     */
    public function get_user_by_token( $token ) {
        $expecteds = get_option( CONTENTSHAKE_API_KEY_OPTION, array() );
        $user_email = array_search( $token, $expecteds, true );

        if ( $user_email ) {
            $user = get_user_by( 'email', $user_email );
            return $user ? $user->ID : 0;
        }

        return 0;
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page( 'Semrush Content Toolkit Settings', 'Content Toolkit', 'manage_options', 'contentshake', array( $this, 'create_settings_page' ) );
    }

    /**
     * Settings page registration
     */
    public function create_settings_page() {
        include plugin_dir_path( __FILE__ ) . '/pages/settings.php';
    }

    /**
     * Filter ContentShake requests for `jwt-auth` plugin.
     */
    public function jwt_auth_whitelist( $default_whitelist ) {
        $rest_api_slug = $this->get_api_slug() . '/' . CONTENTSHAKE_NAMESPACE;
        array_push($default_whitelist, $rest_api_slug . '/');
        array_push($default_whitelist, $this->get_api_slug() . '/' . CONTENTSHAKE_NAMESPACE_V2 . '/');
        return $default_whitelist;
    }

    /**
     * Let plugin routes through `api-bearer-auth`.
     *
     * It rejects every anonymous REST request from `rest_authentication_errors` at
     * priority 10 unless the URL is on its whitelist, and its URL matching breaks on
     * sites with a path in the URL or with `?rest_route=`. Right after it, at priority 11,
     * its `api_bearer_auth_not_logged_in` error is dropped for our own routes when it is
     * the only error, so errors that other plugins return themselves (security,
     * maintenance, IP bans) stay. A filter between the two that only passes that error
     * on cannot be told apart, so its block is dropped as well.
     * The result goes back to `null` rather than `true`, so the core checks at priorities
     * 90 and 100 (application passwords, cookie nonce) still run; each route then enforces
     * its own `permission_callback`, which checks the plugin token.
     */
    public function allow_plugin_routes( $result ) {
        if ( ! is_wp_error( $result ) || array( 'api_bearer_auth_not_logged_in' ) !== $result->get_error_codes() ) {
            return $result;
        }

        $rest_route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? $GLOBALS['wp']->query_vars['rest_route'] : '';
        if ( 0 === strpos( $rest_route, '/' . CONTENTSHAKE_NAMESPACE . '/' ) ||
            0 === strpos( $rest_route, '/' . CONTENTSHAKE_NAMESPACE_V2 . '/' ) ) {
            return null;
        }

        return $result;
    }

    /**
     * Get Rest API prefix.
     */
    private function get_api_slug() {
        $rest_api_slug = get_option( 'permalink_structure' ) ? rest_get_url_prefix() : '?rest_route=';
        $rest_api_slug = home_url( '/' . $rest_api_slug, 'relative' );
        return $rest_api_slug;
    }

}

WP_Semrush_ContentShake::getInstance();
