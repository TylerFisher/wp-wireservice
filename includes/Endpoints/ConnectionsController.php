<?php
declare(strict_types=1);
/**
 * REST API controller for connections.
 *
 * @package Wireservice
 */

namespace Wireservice\Endpoints;

if (! defined('ABSPATH')) {
  exit;
}

use Wireservice\ConnectionsManager;
use Wireservice\API;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ConnectionsController extends WP_REST_Controller {

    /**
     * Namespace for the REST API.
     *
     * @var string
     */
    protected $namespace = 'wireservice/v1';

    /**
     * Resource name.
     *
     * @var string
     */
    protected $rest_base = 'connection';

    /**
     * Constructor.
     */
    public function __construct(
        private ConnectionsManager $connections_manager,
        private API $api,
    ) {}

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                ),
                'schema' => array( $this, 'get_public_item_schema' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/authorize-url',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_authorize_url' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/session',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_session' ),
                    'permission_callback' => array( $this, 'get_item_permissions_check' ),
                ),
            )
        );
    }

    /**
     * Check if a given request has access to get connection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error
     */
    public function get_item_permissions_check( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this resource.', 'wireservice' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check if a given request has access to delete connection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        return $this->get_item_permissions_check( $request );
    }

    /**
     * Get the connection status and info.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_item( $request ) {
        $connection = $this->connections_manager->get_connection();

        $data = array(
            'connected' => ! empty( $connection['access_token'] ),
            'handle'    => $connection['handle'] ?? null,
            'did'       => $connection['did'] ?? null,
        );

        return rest_ensure_response( $data );
    }

    /**
     * Delete the connection.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_item( $request ) {
        delete_option( 'wireservice_connection' );

        return rest_ensure_response(
            array(
                'deleted' => true,
            )
        );
    }

    /**
     * Get the authorize URL.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_authorize_url( $request ) {
        $url = $this->connections_manager->get_authorize_url();

        if ( empty( $url ) ) {
            return new WP_Error(
                'authorize_url_failed',
                __( 'Failed to generate authorization URL.', 'wireservice' ),
                array( 'status' => 500 )
            );
        }

        return rest_ensure_response(
            array(
                'url' => $url,
            )
        );
    }

    /**
     * Get session info from the API.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_session( $request ) {
        if ( ! $this->connections_manager->is_connected() ) {
            return new WP_Error(
                'not_connected',
                __( 'Not connected to AT Protocol.', 'wireservice' ),
                array( 'status' => 400 )
            );
        }

        $session = $this->api->get_session();

        if ( is_wp_error( $session ) ) {
            return $session;
        }

        // Only return non-sensitive session fields.
        return rest_ensure_response( array(
            'handle'       => $session['handle'] ?? null,
            'did'          => $session['did'] ?? null,
            'pds_endpoint' => $session['pds_endpoint'] ?? null,
        ) );
    }

    /**
     * Get the item schema.
     *
     * @return array
     */
    public function get_item_schema(): array {
        if ( $this->schema ) {
            return $this->add_additional_fields_schema( $this->schema );
        }

        $this->schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'wireservice-connection',
            'type'       => 'object',
            'properties' => array(
                'connected' => array(
                    'description' => __( 'Whether connected to AT Protocol.', 'wireservice' ),
                    'type'        => 'boolean',
                    'readonly'    => true,
                ),
                'handle'    => array(
                    'description' => __( 'The connected account handle.', 'wireservice' ),
                    'type'        => array( 'string', 'null' ),
                    'readonly'    => true,
                ),
                'did'       => array(
                    'description' => __( 'The connected account DID.', 'wireservice' ),
                    'type'        => array( 'string', 'null' ),
                    'readonly'    => true,
                ),
            ),
        );

        return $this->add_additional_fields_schema( $this->schema );
    }
}
