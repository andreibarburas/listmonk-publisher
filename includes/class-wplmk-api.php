<?php
defined( 'ABSPATH' ) || exit;

class WPLMK_API {

    private string $base_url;
    private string $api_user;
    private string $api_token;

    public function __construct() {
        $this->base_url  = untrailingslashit( get_option( 'wplmk_url', '' ) );
        $this->api_user  = get_option( 'wplmk_api_user', '' );
        $this->api_token = get_option( 'wplmk_api_token', '' );
    }

    /**
     * Returns true if all required credentials are configured.
     */
    public function is_configured(): bool {
        return ! empty( $this->base_url )
            && ! empty( $this->api_user )
            && ! empty( $this->api_token );
    }

    /**
     * Make an authenticated JSON request to the listmonk API.
     *
     * @param  string $endpoint  e.g. '/api/campaigns'
     * @param  string $method    GET | POST | PUT | DELETE
     * @param  array  $body      Data to encode as JSON body.
     * @return array|WP_Error
     */
    public function request( string $endpoint, string $method = 'GET', array $body = [] ): array|WP_Error {
        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 15,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $this->api_user . ':' . $this->api_token ),
            ],
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $this->base_url . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $message = $data['message'] ?? "HTTP {$code}";
            return new WP_Error( 'wplmk_api_error', $message );
        }

        return $data['data'] ?? $data;
    }

    /**
     * Create a campaign in draft status.
     */
    public function create_campaign( string $name, string $subject, string $body_html, array $list_ids, string $from_email = '', ?int $template_id = null ): array|WP_Error {
        $payload = [
            'name'         => $name,
            'subject'      => $subject,
            'lists'        => array_map( 'intval', $list_ids ),
            'type'         => 'regular',
            'content_type' => 'html',
            'messenger'    => 'email',
            'body'         => $body_html,
        ];

        if ( $template_id ) {
            $payload['template_id'] = $template_id;
        }

        if ( $from_email ) {
            $payload['from_email'] = $from_email;
        }

        return $this->request( '/api/campaigns', 'POST', $payload );
    }

    /**
     * Set a campaign status to 'running', which triggers sending.
     */
    public function start_campaign( int $campaign_id ): array|WP_Error {
        return $this->request(
            '/api/campaigns/' . $campaign_id . '/status',
            'PUT',
            [ 'status' => 'running' ]
        );
    }

    /**
     * Delete a campaign by ID.
     */
    public function delete_campaign( int $campaign_id ): array|WP_Error {
        return $this->request( '/api/campaigns/' . $campaign_id, 'DELETE' );
    }

    /**
     * Fetch all lists — used for the connection test.
     */
    public function get_lists(): array|WP_Error {
        return $this->request( '/api/lists' );
    }
}
