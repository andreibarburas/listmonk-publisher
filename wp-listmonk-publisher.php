<?php
/**
 * Plugin Name:       WP Listmonk Publisher
 * Plugin URI:        https://github.com/andreibarburas/wp-listmonk-publisher
 * Description:       Automatically creates and sends a listmonk campaign when a new post is published, including the featured image, title, opening excerpt, and a read-more link.
 * Version:           1.0.0
 * Author:            Andrei Barburas
 * Author URI:        https://barburas.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-listmonk-publisher
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WPLMK_VERSION', '1.0.0' );
define( 'WPLMK_DIR',     plugin_dir_path( __FILE__ ) );

require_once WPLMK_DIR . 'includes/class-wplmk-api.php';
require_once WPLMK_DIR . 'includes/class-wplmk-campaign.php';
require_once WPLMK_DIR . 'includes/class-wplmk-email-builder.php';
require_once WPLMK_DIR . 'admin/class-wplmk-settings.php';

function wplmk_init(): void {
    new WPLMK_Settings();
    new WPLMK_Campaign();
}
add_action( 'plugins_loaded', 'wplmk_init' );


// ─────────────────────────────────────────────────────────────────────────────
// AUTO-UPDATES VIA GITHUB RELEASES
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'pre_set_site_transient_update_plugins', 'wplmk_check_for_update' );
function wplmk_check_for_update( object $transient ): object {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }

    $plugin_slug = plugin_basename( __FILE__ );
    $repo        = 'andreibarburas/wp-listmonk-publisher';
    $response    = wplmk_get_github_release( $repo );

    if ( is_wp_error( $response ) ) {
        return $transient;
    }

    $release = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $release['tag_name'] ) ) {
        return $transient;
    }

    $latest_version = ltrim( $release['tag_name'], 'v' );
    if ( version_compare( $latest_version, WPLMK_VERSION, '>' ) ) {
        $zip_url = wplmk_get_release_zip_url( $release, $repo );
        $transient->response[ $plugin_slug ] = (object) [
            'slug'        => dirname( $plugin_slug ),
            'plugin'      => $plugin_slug,
            'new_version' => $latest_version,
            'url'         => "https://github.com/{$repo}",
            'package'     => $zip_url,
        ];
    }

    return $transient;
}

add_filter( 'plugins_api', 'wplmk_plugin_info', 10, 3 );
function wplmk_plugin_info( mixed $result, string $action, object $args ): mixed {
    if ( 'plugin_information' !== $action ) {
        return $result;
    }
    if ( dirname( plugin_basename( __FILE__ ) ) !== $args->slug ) {
        return $result;
    }

    $repo     = 'andreibarburas/wp-listmonk-publisher';
    $response = wplmk_get_github_release( $repo );

    if ( is_wp_error( $response ) ) {
        return $result;
    }

    $release = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $release['tag_name'] ) ) {
        return $result;
    }

    return (object) [
        'name'          => 'WP Listmonk Publisher',
        'slug'          => dirname( plugin_basename( __FILE__ ) ),
        'version'       => ltrim( $release['tag_name'], 'v' ),
        'author'        => '<a href="https://barburas.com">Andrei Barburas</a>',
        'homepage'      => "https://github.com/{$repo}",
        'requires'      => '6.0',
        'requires_php'  => '8.0',
        'sections'      => [
            'description' => $release['body'] ?? 'See GitHub for release notes.',
        ],
        'download_link' => wplmk_get_release_zip_url( $release, $repo ),
    ];
}

function wplmk_get_github_release( string $repo ): array|WP_Error {
    $cached = get_transient( 'wplmk_github_release' );
    if ( false !== $cached ) {
        return $cached;
    }

    $response = wp_remote_get( "https://api.github.com/repos/{$repo}/releases/latest", [
        'headers' => [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
        ],
        'timeout' => 10,
    ] );

    if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
        set_transient( 'wplmk_github_release', $response, 6 * HOUR_IN_SECONDS );
    }

    return $response;
}

function wplmk_get_release_zip_url( array $release, string $repo ): string {
    foreach ( $release['assets'] ?? [] as $asset ) {
        if ( str_ends_with( $asset['name'], '.zip' ) ) {
            return $asset['browser_download_url'];
        }
    }
    return "https://github.com/{$repo}/archive/refs/tags/{$release['tag_name']}.zip";
}

register_deactivation_hook( __FILE__, function(): void {
    delete_transient( 'wplmk_github_release' );
} );
