<?php
defined( 'ABSPATH' ) || exit;

class WPLMK_Settings {

    public function __construct() {
        add_action( 'admin_menu',              [ $this, 'register_menu' ] );
        add_action( 'admin_init',              [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_wplmk_test',      [ $this, 'ajax_test' ] );
        add_action( 'wp_ajax_wplmk_clear_log', [ $this, 'ajax_clear_log' ] );
        add_action( 'wp_ajax_wplmk_send_test', [ $this, 'ajax_send_test' ] );
    }

    public function register_menu(): void {
        add_options_page(
            'WP Listmonk Publisher',
            'Listmonk Publisher',
            'manage_options',
            'wp-listmonk-publisher',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        $options = [
            'wplmk_enabled', 'wplmk_url', 'wplmk_api_user',
            'wplmk_api_token', 'wplmk_list_ids', 'wplmk_from_email',
            'wplmk_send_mode', 'wplmk_template_id', 'wplmk_categories',
        ];
        foreach ( $options as $opt ) {
            register_setting( 'wplmk_group', $opt );
        }

        // Separate group so saving the test form doesn't touch main settings.
        register_setting( 'wplmk_test_group', 'wplmk_test_list_id' );
        register_setting( 'wplmk_test_group', 'wplmk_test_template_id' );
    }

    public function ajax_test(): void {
        check_ajax_referer( 'wplmk_nonce', 'nonce' );
        $api    = new WPLMK_API();
        $result = $api->get_lists();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        $count = count( $result['results'] ?? [] );
        wp_send_json_success( "Connection successful. {$count} list(s) found." );
    }

    public function ajax_clear_log(): void {
        check_ajax_referer( 'wplmk_nonce', 'nonce' );
        update_option( 'wplmk_log', [], false );
        wp_send_json_success();
    }

    public function ajax_send_test(): void {
        check_ajax_referer( 'wplmk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $test_list_id = (int) get_option( 'wplmk_test_list_id', 0 );
        if ( ! $test_list_id ) {
            wp_send_json_error( 'No test list ID configured.' );
        }

        $posts = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish' ] );
        if ( empty( $posts ) ) {
            wp_send_json_error( 'No published posts found.' );
        }

        $post             = $posts[0];
        $title            = get_the_title( $post );
        $from_email       = get_option( 'wplmk_from_email', '' );
        $test_template_id = (int) get_option( 'wplmk_test_template_id', 0 );
        $main_template_id = (int) get_option( 'wplmk_template_id', 0 );
        $template_id      = $test_template_id > 0 ? $test_template_id : $main_template_id;

        $body_html = WPLMK_Email_Builder::build( $post );

        $api = new WPLMK_API();

        if ( ! $api->is_configured() ) {
            wp_send_json_error( 'Plugin is not fully configured.' );
        }

        $campaign = $api->create_campaign(
            '[TEST] ' . $title,
            '[TEST] ' . $title,
            $body_html,
            [ $test_list_id ],
            $from_email,
            $template_id > 0 ? $template_id : null
        );

        if ( is_wp_error( $campaign ) ) {
            wp_send_json_error( 'Could not create test campaign: ' . $campaign->get_error_message() );
        }

        $campaign_id = $campaign['id'] ?? null;
        if ( ! $campaign_id ) {
            wp_send_json_error( 'Campaign created but no ID returned.' );
        }

        $result = $api->start_campaign( (int) $campaign_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( 'Campaign created but could not be started: ' . $result->get_error_message() );
        }

        wp_send_json_success( "Test campaign #{$campaign_id} sent to list #{$test_list_id} using \"{$title}\"." );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $enabled          = get_option( 'wplmk_enabled', '0' );
        $url              = get_option( 'wplmk_url', '' );
        $api_user         = get_option( 'wplmk_api_user', '' );
        $api_token        = get_option( 'wplmk_api_token', '' );
        $list_ids         = get_option( 'wplmk_list_ids', [] );
        $from_email       = get_option( 'wplmk_from_email', '' );
        $send_mode        = get_option( 'wplmk_send_mode', 'immediate' );
        $template_id      = get_option( 'wplmk_template_id', '' );
        $saved_categories = (array) get_option( 'wplmk_categories', [] );
        $test_list_id     = get_option( 'wplmk_test_list_id', '' );
        $test_template_id = get_option( 'wplmk_test_template_id', '' );
        $log              = array_reverse( get_option( 'wplmk_log', [] ) );
        $list_ids_str     = is_array( $list_ids ) ? implode( ', ', $list_ids ) : $list_ids;
        $nonce            = wp_create_nonce( 'wplmk_nonce' );
        $all_categories   = get_categories( [ 'hide_empty' => false ] );
        ?>
        <div class="wplmk-wrap">

        <style>
        .wplmk-wrap {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 760px;
            margin: 32px 0;
            color: #1d2327;
        }
        .wplmk-wrap h1 { font-size: 20px; font-weight: 600; margin: 0 0 4px; color: #1d2327; }
        .wplmk-subtitle { color: #646970; font-size: 13px; margin: 0 0 32px; }
        .wplmk-card { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 24px 28px; margin-bottom: 20px; }
        .wplmk-card h2 { font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #646970; margin: 0 0 20px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
        .wplmk-field { display: grid; grid-template-columns: 180px 1fr; align-items: start; gap: 8px 16px; margin-bottom: 16px; }
        .wplmk-field:last-child { margin-bottom: 0; }
        .wplmk-field label { font-size: 13px; font-weight: 500; padding-top: 7px; color: #1d2327; }
        .wplmk-field .wplmk-hint { font-size: 12px; color: #646970; margin-top: 4px; line-height: 1.5; }
        .wplmk-wrap input[type="text"],
        .wplmk-wrap input[type="email"],
        .wplmk-wrap input[type="password"],
        .wplmk-wrap select { width: 100%; max-width: 440px; padding: 6px 10px; font-size: 13px; border: 1px solid #dcdcde; border-radius: 3px; box-shadow: inset 0 1px 2px rgba(0,0,0,.05); outline: none; transition: border-color .15s; }
        .wplmk-wrap input:focus, .wplmk-wrap select:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .wplmk-toggle { display: flex; align-items: center; gap: 10px; padding-top: 4px; }
        .wplmk-toggle input[type="checkbox"] { width: 16px; height: 16px; margin: 0; cursor: pointer; }
        .wplmk-toggle span { font-size: 13px; color: #1d2327; }
        .wplmk-actions { display: flex; gap: 10px; align-items: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
        .wplmk-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; font-size: 13px; font-weight: 500; border-radius: 3px; cursor: pointer; border: 1px solid transparent; text-decoration: none; transition: background .15s, border-color .15s; }
        .wplmk-btn-primary { background: #2271b1; color: #fff; border-color: #2271b1; }
        .wplmk-btn-primary:hover { background: #135e96; border-color: #135e96; color: #fff; }
        .wplmk-btn-secondary { background: #f6f7f7; color: #2271b1; border-color: #dcdcde; }
        .wplmk-btn-secondary:hover { background: #f0f0f0; }
        .wplmk-btn-danger { background: #fff; color: #b32d2e; border-color: #dcdcde; font-size: 12px; padding: 5px 12px; }
        .wplmk-btn-danger:hover { background: #fcf0f1; border-color: #b32d2e; }
        #wplmk-test-result { font-size: 13px; padding: 6px 12px; border-radius: 3px; display: none; }
        #wplmk-test-result.ok  { background: #edfaef; color: #1a7f37; border: 1px solid #b3e6c0; }
        #wplmk-test-result.err { background: #fcf0f1; color: #b32d2e; border: 1px solid #f5b9bb; }
        .wplmk-log { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 3px; padding: 14px 16px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.7; max-height: 260px; overflow-y: auto; color: #3c434a; }
        .wplmk-log .log-empty { color: #646970; font-style: italic; }
        .wplmk-log-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .wplmk-log-header h2 { margin: 0; border: none; padding: 0; }
        </style>

        <h1>WP Listmonk Publisher</h1>
        <p class="wplmk-subtitle">Sends the opening excerpt of each new post to your listmonk subscribers automatically.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'wplmk_group' ); ?>

            <div class="wplmk-card">
                <h2>Status</h2>
                <div class="wplmk-field">
                    <label>Active</label>
                    <div>
                        <div class="wplmk-toggle">
                            <input type="checkbox" name="wplmk_enabled" id="wplmk_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
                            <span>Send campaigns automatically when a post is published</span>
                        </div>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_send_mode">Send mode</label>
                    <div>
                        <select name="wplmk_send_mode" id="wplmk_send_mode">
                            <option value="immediate" <?php selected( $send_mode, 'immediate' ); ?>>Immediate — start sending right away</option>
                            <option value="draft"     <?php selected( $send_mode, 'draft' ); ?>>Draft — create campaign, send manually in listmonk</option>
                        </select>
                        <p class="wplmk-hint">Draft mode is useful for reviewing the campaign before it goes out.</p>
                    </div>
                </div>
            </div>

            <div class="wplmk-card">
                <h2>Listmonk Connection</h2>
                <div class="wplmk-field">
                    <label for="wplmk_url">Server URL</label>
                    <div>
                        <input type="text" name="wplmk_url" id="wplmk_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://listmonk.yoursite.com">
                        <p class="wplmk-hint">No trailing slash.</p>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_api_user">API username</label>
                    <input type="text" name="wplmk_api_user" id="wplmk_api_user" value="<?php echo esc_attr( $api_user ); ?>" autocomplete="off">
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_api_token">API token</label>
                    <div>
                        <input type="password" name="wplmk_api_token" id="wplmk_api_token" value="<?php echo esc_attr( $api_token ); ?>" autocomplete="new-password">
                        <p class="wplmk-hint">Create an API user under <strong>Admin → Users</strong> in listmonk.</p>
                    </div>
                </div>
            </div>

            <div class="wplmk-card">
                <h2>Campaign Settings</h2>
                <div class="wplmk-field">
                    <label for="wplmk_template_id">Template ID</label>
                    <div>
                        <input type="text" name="wplmk_template_id" id="wplmk_template_id" value="<?php echo esc_attr( $template_id ); ?>" placeholder="e.g. 1">
                        <p class="wplmk-hint">Optional. The ID of a listmonk template to wrap the email. Leave blank to send without a template.</p>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_list_ids">List ID(s)</label>
                    <div>
                        <input type="text" name="wplmk_list_ids" id="wplmk_list_ids" value="<?php echo esc_attr( $list_ids_str ); ?>" placeholder="1, 2">
                        <p class="wplmk-hint">Comma-separated list IDs from your listmonk dashboard.</p>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_from_email">From email</label>
                    <div>
                        <input type="text" name="wplmk_from_email" id="wplmk_from_email" value="<?php echo esc_attr( $from_email ); ?>" placeholder="Newsletter <hello@yoursite.com>">
                        <p class="wplmk-hint">Leave blank to use listmonk's default sender. Note: listmonk rejects display names containing dots — use a plain word, e.g. <code>1984black &lt;noreply@mailing.1984.black&gt;</code>.</p>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label>Categories</label>
                    <div>
                        <?php if ( empty( $all_categories ) ) : ?>
                            <p class="wplmk-hint">No categories found.</p>
                        <?php else : ?>
                            <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px;">
                                <?php foreach ( $all_categories as $cat ) : ?>
                                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;padding-top:0;cursor:pointer;">
                                        <input type="checkbox"
                                               name="wplmk_categories[]"
                                               value="<?php echo esc_attr( $cat->term_id ); ?>"
                                               <?php checked( in_array( (string) $cat->term_id, array_map( 'strval', $saved_categories ), true ) ); ?>
                                               style="width:auto;max-width:none;box-shadow:none;">
                                        <?php echo esc_html( $cat->name ); ?>
                                        <span style="color:#8c8f94;font-size:11px;">(<?php echo (int) $cat->count; ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="wplmk-hint" style="margin-top:8px;">Only posts in the selected categories will trigger a newsletter. Leave all unchecked to send for every category.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="wplmk-actions">
                <?php submit_button( 'Save settings', 'primary wplmk-btn wplmk-btn-primary', 'submit', false ); ?>
                <button type="button" class="wplmk-btn wplmk-btn-secondary" id="wplmk-test-btn" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    Test connection
                </button>
                <span id="wplmk-test-result"></span>
            </div>
        </form>

        <div class="wplmk-card" style="margin-top:20px;">
            <h2>Send a test email</h2>
            <p style="font-size:13px;color:#646970;margin:0 0 16px;">
                Creates a real campaign from the most recently published post and sends it to a dedicated test list in listmonk.
                Subscribers on that list receive it exactly as a live campaign would — same template, same pipeline.
            </p>
            <form method="post" action="options.php" style="margin-bottom:16px;">
                <?php settings_fields( 'wplmk_test_group' ); ?>
                <div class="wplmk-field">
                    <label for="wplmk_test_list_id">Test list ID</label>
                    <div>
                        <input type="text" name="wplmk_test_list_id" id="wplmk_test_list_id" value="<?php echo esc_attr( $test_list_id ); ?>" placeholder="e.g. 2">
                        <p class="wplmk-hint">Create a private list in listmonk with only yourself as a subscriber. Enter its ID here.</p>
                    </div>
                </div>
                <div class="wplmk-field">
                    <label for="wplmk_test_template_id">Test template ID</label>
                    <div>
                        <input type="text" name="wplmk_test_template_id" id="wplmk_test_template_id" value="<?php echo esc_attr( $test_template_id ); ?>" placeholder="e.g. 1">
                        <p class="wplmk-hint">Leave blank to use the Template ID from Campaign Settings above.</p>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <?php submit_button( 'Save test settings', 'secondary wplmk-btn wplmk-btn-secondary', 'submit', false ); ?>
                </div>
            </form>
            <div style="display:flex;align-items:center;gap:12px;padding-top:16px;border-top:1px solid #f0f0f0;">
                <button type="button" class="wplmk-btn wplmk-btn-primary" id="wplmk-send-test-btn" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    Send test email
                </button>
                <span id="wplmk-send-test-result" style="font-size:13px;padding:6px 12px;border-radius:3px;display:none;"></span>
            </div>
        </div>

        <div class="wplmk-card" style="margin-top:20px;">
            <div class="wplmk-log-header">
                <h2 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#646970;">Activity log</h2>
                <button type="button" class="wplmk-btn wplmk-btn-danger" id="wplmk-clear-log" data-nonce="<?php echo esc_attr( $nonce ); ?>">Clear log</button>
            </div>
            <div class="wplmk-log" id="wplmk-log-output">
                <?php if ( empty( $log ) ) : ?>
                    <span class="log-empty">No activity yet.</span>
                <?php else : ?>
                    <?php foreach ( $log as $line ) : ?>
                        <div><?php echo esc_html( $line ); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        </div><!-- .wplmk-wrap -->

        <script>
        (function() {
            document.querySelector('form').addEventListener('submit', function() {
                var raw = document.getElementById('wplmk_list_ids').value;
                var ids = raw.split(',').map(function(s){ return parseInt(s.trim(), 10); }).filter(Boolean);
                document.getElementById('wplmk_list_ids').value = ids.join(', ');
            });

            function ajaxBtn(btnId, resultId, action, opts) {
                document.getElementById(btnId).addEventListener('click', function() {
                    var btn    = this;
                    var result = document.getElementById(resultId);
                    btn.disabled = true;
                    btn.textContent = opts.loading || 'Working…';
                    result.style.display = 'none';

                    var data = new FormData();
                    data.append('action', action);
                    data.append('nonce', btn.dataset.nonce);

                    fetch(ajaxurl, { method: 'POST', body: data })
                        .then(function(r){ return r.json(); })
                        .then(function(r) {
                            result.style.display = 'inline-block';
                            if (r.success) {
                                result.className = 'ok';
                                result.style.cssText = 'display:inline-block;padding:6px 12px;border-radius:3px;background:#edfaef;color:#1a7f37;border:1px solid #b3e6c0;font-size:13px;';
                                result.textContent = r.data;
                            } else {
                                result.className = 'err';
                                result.style.cssText = 'display:inline-block;padding:6px 12px;border-radius:3px;background:#fcf0f1;color:#b32d2e;border:1px solid #f5b9bb;font-size:13px;';
                                result.textContent = 'Error: ' + r.data;
                            }
                        })
                        .catch(function() {
                            result.style.display = 'inline-block';
                            result.textContent = 'Request failed.';
                        })
                        .finally(function() {
                            btn.disabled = false;
                            btn.textContent = opts.label;
                        });
                });
            }

            ajaxBtn('wplmk-test-btn',      'wplmk-test-result',      'wplmk_test',      { label: 'Test connection', loading: 'Testing…' });
            ajaxBtn('wplmk-send-test-btn',  'wplmk-send-test-result', 'wplmk_send_test', { label: 'Send test email', loading: 'Sending…' });

            document.getElementById('wplmk-clear-log').addEventListener('click', function() {
                if (!confirm('Clear the activity log?')) return;
                var btn  = this;
                var data = new FormData();
                data.append('action', 'wplmk_clear_log');
                data.append('nonce', btn.dataset.nonce);
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(){
                        document.getElementById('wplmk-log-output').innerHTML =
                            '<span class="log-empty">No activity yet.</span>';
                    });
            });
        })();
        </script>
        <?php
    }
}
