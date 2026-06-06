<?php
defined( 'ABSPATH' ) || exit;

class WPLMK_Campaign {

    public function __construct() {
        add_action( 'transition_post_status', [ $this, 'on_publish' ], 10, 3 );
    }

    /**
     * Fires whenever a post changes status.
     * We act only on the initial transition to 'publish'.
     */
    public function on_publish( string $new_status, string $old_status, WP_Post $post ): void {
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }

        if ( $post->post_type !== 'post' ) {
            return;
        }

        if ( get_option( 'wplmk_enabled', '0' ) !== '1' ) {
            return;
        }

        $api = new WPLMK_API();

        if ( ! $api->is_configured() ) {
            $this->log( 'Plugin not fully configured — campaign not sent.' );
            return;
        }

        $list_ids    = get_option( 'wplmk_list_ids', [] );
        $from_email  = get_option( 'wplmk_from_email', '' );
        $send_mode   = get_option( 'wplmk_send_mode', 'immediate' );
        $template_id = (int) get_option( 'wplmk_template_id', 0 );

        // Category filter — if any categories are selected, the post must belong to at least one.
        $allowed_cats = array_filter( array_map( 'intval', (array) get_option( 'wplmk_categories', [] ) ) );
        if ( ! empty( $allowed_cats ) ) {
            $post_cats = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
            if ( empty( array_intersect( $allowed_cats, $post_cats ) ) ) {
                $this->log( "Post #{$post->ID} skipped — not in a selected category." );
                return;
            }
        }

        if ( empty( $list_ids ) ) {
            $this->log( 'No list IDs configured — campaign not sent.' );
            return;
        }

        $title     = get_the_title( $post );
        $body_html = WPLMK_Email_Builder::build( $post );

        $campaign = $api->create_campaign(
            $title,
            $title,
            $body_html,
            (array) $list_ids,
            $from_email,
            $template_id > 0 ? $template_id : null
        );

        if ( is_wp_error( $campaign ) ) {
            $this->log( 'Failed to create campaign: ' . $campaign->get_error_message() );
            return;
        }

        $campaign_id = $campaign['id'] ?? null;

        if ( ! $campaign_id ) {
            $this->log( 'Campaign created but no ID returned.' );
            return;
        }

        $this->log( "Campaign #{$campaign_id} created for \"{$title}\" (post #{$post->ID})." );

        if ( $send_mode === 'immediate' ) {
            $result = $api->start_campaign( (int) $campaign_id );
            if ( is_wp_error( $result ) ) {
                $this->log( 'Failed to start campaign: ' . $result->get_error_message() );
            } else {
                $this->log( "Campaign #{$campaign_id} started — sending to subscribers." );
            }
        } else {
            $this->log( "Campaign #{$campaign_id} saved as draft. Send manually in listmonk." );
        }
    }

    /**
     * Append a timestamped entry to the plugin activity log (capped at 100 lines).
     */
    private function log( string $message ): void {
        $log   = get_option( 'wplmk_log', [] );
        $log[] = '[' . current_time( 'Y-m-d H:i:s' ) . '] ' . $message;

        if ( count( $log ) > 100 ) {
            $log = array_slice( $log, -100 );
        }

        update_option( 'wplmk_log', $log, false );
    }
}
