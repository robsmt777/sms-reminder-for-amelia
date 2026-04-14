<?php
/**
 * Settings page: Settings → SMS Reminder (v1.1+ with slots)
 *
 * Included by srfa_render_settings_page() — WordPress already loaded, user is admin.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Current option + save status ─────────────────────────────────────────────
$saved    = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';
$options  = get_option( SRFA_OPTION_KEY, [] );

$cur_api_key   = srfa_get_option( 'api_key' );
$cur_sender    = srfa_get_option( 'sender' );
$cur_sandbox   = (bool) srfa_get_option( 'sandbox' );
$cur_purge     = (int) srfa_get_option( 'purge_days' );
$cur_cron_freq = (int) srfa_get_option( 'cron_frequency', 15 );

$locked_api    = srfa_is_locked( 'api_key' );
$locked_sender = srfa_is_locked( 'sender' );
$locked_sbox   = srfa_is_locked( 'sandbox' );

$slots   = srfa_get_slots();
$offsets = srfa_reminder_offsets();

// ── Template preview (server-side, fake data) ────────────────────────────────
$preview_vars = [
    '%location_name%'          => __( 'Paris Salon',    'sms-reminder-for-amelia' ),
    '%customer_first_name%'    => __( 'Sophie',         'sms-reminder-for-amelia' ),
    '%customer_last_name%'     => __( 'Martin',         'sms-reminder-for-amelia' ),
    '%customer_full_name%'     => __( 'Sophie Martin',  'sms-reminder-for-amelia' ),
    '%employee_first_name%'    => __( 'Claire',         'sms-reminder-for-amelia' ),
    '%employee_last_name%'     => __( 'Dupont',         'sms-reminder-for-amelia' ),
    '%employee_full_name%'     => __( 'Claire Dupont',  'sms-reminder-for-amelia' ),
    '%service_name%'           => __( 'Facial care',    'sms-reminder-for-amelia' ),
    '%appointment_date%'       => __( 'Monday April 14', 'sms-reminder-for-amelia' ),
    '%appointment_start_time%' => '14:30',
];

function srfa_render_slot_preview( $template, $vars, $slot_num ) {
    $msg  = str_replace( array_keys( $vars ), array_values( $vars ), $template );
    $len  = mb_strlen( $msg );
    $segs = max( 1, (int) ceil( $len / 160 ) );
    ?>
    <div style="margin-top:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;">
        <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
            <?php
            printf(
                /* translators: %d: slot number. */
                esc_html__( 'SMS %d preview (sample data)', 'sms-reminder-for-amelia' ),
                (int) $slot_num
            );
            ?>
        </div>
        <div id="srfa_preview_text_<?php echo (int) $slot_num; ?>"
             style="font-size:13px;color:#1e293b;line-height:1.5;word-break:break-word;">
            <?php echo esc_html( $msg ); ?>
        </div>
        <div style="margin-top:8px;font-size:12px;color:#64748b;">
            <span id="srfa_preview_chars_<?php echo (int) $slot_num; ?>"><?php echo esc_html( $len ); ?></span> <?php esc_html_e( 'characters', 'sms-reminder-for-amelia' ); ?>
            — <span id="srfa_preview_segs_<?php echo (int) $slot_num; ?>"><?php echo esc_html( $segs ); ?></span> <?php esc_html_e( 'SMS segment(s)', 'sms-reminder-for-amelia' ); ?>
            <span style="color:#94a3b8;"><?php esc_html_e( '(160 chars/segment)', 'sms-reminder-for-amelia' ); ?></span>
        </div>
    </div>
    <?php
}

?>
<div class="wrap" style="max-width:920px;">

    <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <span style="font-size:26px;">⚙️</span> <?php esc_html_e( 'SMS Reminder — Settings', 'sms-reminder-for-amelia' ); ?>
    </h1>
    <p style="color:#64748b;margin-top:0;margin-bottom:24px;">
        <?php esc_html_e( 'Configuration of the SMS appointment reminder plugin.', 'sms-reminder-for-amelia' ); ?>
        <a href="<?php echo esc_url( admin_url( 'tools.php?page=srfa-logs' ) ); ?>">← <?php esc_html_e( 'View logs', 'sms-reminder-for-amelia' ); ?></a>
    </p>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible" style="margin-bottom:20px;">
            <p><strong><?php esc_html_e( 'Settings saved.', 'sms-reminder-for-amelia' ); ?></strong></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'srfa_settings_group' ); ?>

        <!-- ══ Section 1: SMS Partner API ══════════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;display:flex;align-items:center;gap:8px;">
                🔑 <?php esc_html_e( 'SMS Partner API', 'sms-reminder-for-amelia' ); ?>
            </h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="srfa_api_key"><?php esc_html_e( 'API key', 'sms-reminder-for-amelia' ); ?></label>
                    </th>
                    <td>
                        <?php if ( $locked_api ) : ?>
                            <input type="text" value="<?php echo esc_attr( substr( $cur_api_key, 0, 6 ) . str_repeat( '•', max( 0, strlen( $cur_api_key ) - 6 ) ) ); ?>"
                                   class="regular-text" disabled readonly style="font-family:monospace;">
                            <?php srfa_locked_badge(); ?>
                        <?php else : ?>
                            <input type="password" id="srfa_api_key"
                                   name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[api_key]"
                                   value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>"
                                   class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo esc_attr__( 'Your SMS Partner API key', 'sms-reminder-for-amelia' ); ?>">
                            <button type="button" onclick="srfa_toggle_key(this)"
                                    style="margin-left:6px;cursor:pointer;background:none;border:1px solid #d1d5db;border-radius:4px;padding:4px 10px;font-size:12px;">
                                <?php esc_html_e( 'Show', 'sms-reminder-for-amelia' ); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e( 'Find your key in SMS Partner dashboard → Settings → API.', 'sms-reminder-for-amelia' ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="srfa_sender"><?php esc_html_e( 'Sender', 'sms-reminder-for-amelia' ); ?></label>
                    </th>
                    <td>
                        <?php if ( $locked_sender ) : ?>
                            <input type="text" value="<?php echo esc_attr( $cur_sender ); ?>"
                                   class="regular-text" disabled readonly>
                            <?php srfa_locked_badge(); ?>
                        <?php else : ?>
                            <input type="text" id="srfa_sender"
                                   name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[sender]"
                                   value="<?php echo esc_attr( $options['sender'] ?? $cur_sender ); ?>"
                                   class="regular-text" maxlength="11"
                                   placeholder="Reminder">
                            <p class="description">
                                <?php esc_html_e( '3 to 11 alphanumeric characters, no spaces or accents. Displayed in place of the phone number on the recipient phone.', 'sms-reminder-for-amelia' ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Sandbox mode', 'sms-reminder-for-amelia' ); ?></th>
                    <td>
                        <?php if ( $locked_sbox ) : ?>
                            <span style="font-weight:600;"><?php echo $cur_sandbox ? '🧪 ' . esc_html__( 'Active (test)', 'sms-reminder-for-amelia' ) : '🚀 ' . esc_html__( 'Inactive (production)', 'sms-reminder-for-amelia' ); ?></span>
                            <?php srfa_locked_badge(); ?>
                        <?php else : ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="srfa_sandbox"
                                       name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[sandbox]"
                                       value="1"
                                       <?php checked( $options['sandbox'] ?? true ); ?>>
                                <span><?php esc_html_e( 'Enable test mode (no SMS actually sent, no credit used)', 'sms-reminder-for-amelia' ); ?></span>
                            </label>
                            <p class="description" style="margin-top:6px;">
                                ⚠️ <?php esc_html_e( 'Disable this to switch to production.', 'sms-reminder-for-amelia' ); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══ Section 2: Reminder slots ═══════════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;">💬 <?php esc_html_e( 'SMS reminders', 'sms-reminder-for-amelia' ); ?></h2>
            <p style="color:#64748b;margin-top:0;margin-bottom:20px;font-size:13px;">
                <?php esc_html_e( 'Configure up to 2 reminders per appointment. SMS 1 is mandatory, SMS 2 is optional. Each slot has its own timing and message.', 'sms-reminder-for-amelia' ); ?>
            </p>

            <?php
            $slot_configs = [
                1 => [
                    'title'      => __( 'SMS 1 (required)', 'sms-reminder-for-amelia' ),
                    'icon'       => '📱',
                    'can_toggle' => false,
                    'bg'         => '#f0f9ff',
                    'border'     => '#7dd3fc',
                ],
                2 => [
                    'title'      => __( 'SMS 2 (optional)', 'sms-reminder-for-amelia' ),
                    'icon'       => '📲',
                    'can_toggle' => true,
                    'bg'         => '#f8fafc',
                    'border'     => '#e2e8f0',
                ],
            ];

            foreach ( $slot_configs as $n => $cfg ) :
                $slot = $slots[ $n ];
            ?>
            <div style="background:<?php echo esc_attr( $cfg['bg'] ); ?>;border:1px solid <?php echo esc_attr( $cfg['border'] ); ?>;border-radius:8px;padding:18px 22px;margin-bottom:16px;">

                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <span style="font-size:22px;"><?php echo esc_html( $cfg['icon'] ); ?></span>
                    <h3 style="margin:0;font-size:15px;flex:1;">
                        <?php echo esc_html( $cfg['title'] ); ?>
                    </h3>
                    <?php if ( $cfg['can_toggle'] ) : ?>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;">
                            <input type="checkbox"
                                   id="srfa_slot_<?php echo (int) $n; ?>_enabled"
                                   name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[slots][<?php echo (int) $n; ?>][enabled]"
                                   value="1"
                                   <?php checked( $slot['enabled'] ); ?>
                                   onchange="srfa_toggle_slot(<?php echo (int) $n; ?>, this.checked)">
                            <span><?php esc_html_e( 'Enable this reminder', 'sms-reminder-for-amelia' ); ?></span>
                        </label>
                    <?php else : ?>
                        <input type="hidden"
                               name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[slots][<?php echo (int) $n; ?>][enabled]"
                               value="1">
                        <span style="font-size:12px;color:#0369a1;background:#e0f2fe;padding:3px 10px;border-radius:12px;font-weight:600;"><?php esc_html_e( 'Always active', 'sms-reminder-for-amelia' ); ?></span>
                    <?php endif; ?>
                </div>

                <div id="srfa_slot_<?php echo (int) $n; ?>_body" style="<?php echo ( ! $slot['enabled'] && $cfg['can_toggle'] ) ? 'opacity:0.45;pointer-events:none;' : ''; ?>">
                    <table class="form-table" role="presentation" style="margin-top:0;">
                        <tr>
                            <th scope="row" style="width:180px;padding-top:6px;padding-bottom:6px;">
                                <label for="srfa_slot_<?php echo (int) $n; ?>_offset"><?php esc_html_e( 'Send', 'sms-reminder-for-amelia' ); ?></label>
                            </th>
                            <td style="padding-top:6px;padding-bottom:6px;">
                                <select id="srfa_slot_<?php echo (int) $n; ?>_offset"
                                        name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[slots][<?php echo (int) $n; ?>][offset_minutes]"
                                        style="min-width:260px;">
                                    <?php foreach ( $offsets as $mins => $label ) : ?>
                                        <option value="<?php echo esc_attr( $mins ); ?>"
                                            <?php selected( (int) $slot['offset_minutes'], $mins ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ( $n === 2 ) : ?>
                                    <p class="description" style="margin-top:6px;">
                                        💡 <?php esc_html_e( 'For very short reminders (< 1h), consider lowering the cron frequency below to 5 min or less.', 'sms-reminder-for-amelia' ); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="vertical-align:top;padding-top:10px;">
                                <label for="srfa_slot_<?php echo (int) $n; ?>_template"><?php esc_html_e( 'Message', 'sms-reminder-for-amelia' ); ?></label>
                            </th>
                            <td>
                                <textarea id="srfa_slot_<?php echo (int) $n; ?>_template"
                                          name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[slots][<?php echo (int) $n; ?>][template]"
                                          rows="3" class="large-text"
                                          style="font-family:monospace;font-size:13px;"
                                          oninput="srfa_update_preview(<?php echo (int) $n; ?>, this.value)"
                                ><?php echo esc_textarea( $slot['template'] ); ?></textarea>

                                <?php srfa_render_slot_preview( $slot['template'], $preview_vars, $n ); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <details style="margin-top:12px;font-size:13px;color:#64748b;">
                <summary style="cursor:pointer;user-select:none;">📖 <?php esc_html_e( 'Available template variables', 'sms-reminder-for-amelia' ); ?></summary>
                <div style="margin-top:10px;padding:12px;background:#f8fafc;border-radius:6px;">
                    <code>%location_name%</code>
                    <code>%customer_first_name%</code>
                    <code>%customer_last_name%</code>
                    <code>%customer_full_name%</code><br>
                    <code>%employee_first_name%</code>
                    <code>%employee_last_name%</code>
                    <code>%employee_full_name%</code><br>
                    <code>%service_name%</code>
                    <code>%appointment_date%</code>
                    <code>%appointment_start_time%</code>
                </div>
            </details>
        </div>

        <!-- ══ Section 3: Cron frequency & logs ════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;">🕐 <?php esc_html_e( 'Cron frequency & logs', 'sms-reminder-for-amelia' ); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="srfa_cron_freq"><?php esc_html_e( 'Check frequency', 'sms-reminder-for-amelia' ); ?></label>
                    </th>
                    <td>
                        <select id="srfa_cron_freq"
                                name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[cron_frequency]"
                                style="min-width:220px;">
                            <?php foreach ( srfa_cron_frequencies() as $minutes => $label ) : ?>
                                <option value="<?php echo esc_attr( $minutes ); ?>"
                                    <?php selected( $cur_cron_freq, $minutes ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'How often the plugin checks for SMS to send. Should match your server cron. Changing this value automatically reschedules the WordPress cron on save.', 'sms-reminder-for-amelia' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="srfa_purge"><?php esc_html_e( 'Keep logs for', 'sms-reminder-for-amelia' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="srfa_purge"
                               name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[purge_days]"
                               value="<?php echo esc_attr( $options['purge_days'] ?? $cur_purge ); ?>"
                               min="7" max="365" step="1" style="width:80px;">
                        <span style="margin-left:6px;color:#64748b;"><?php esc_html_e( 'days', 'sms-reminder-for-amelia' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Automatic purge runs every Monday at 3am. (7–365 days)', 'sms-reminder-for-amelia' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save settings', 'sms-reminder-for-amelia' ), 'primary large' ); ?>
    </form>

    <!-- ── System info ─────────────────────────────────────────────────── -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-top:24px;">
        <h3 style="margin-top:0;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">
            <?php esc_html_e( 'System info', 'sms-reminder-for-amelia' ); ?>
        </h3>
        <table style="font-size:12px;color:#475569;border-collapse:collapse;width:100%;">
            <tr>
                <td style="padding:3px 0;width:220px;color:#94a3b8;"><?php esc_html_e( 'Plugin version', 'sms-reminder-for-amelia' ); ?></td>
                <td><?php echo esc_html( SRFA_VERSION ); ?></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;"><?php esc_html_e( 'DLR webhook endpoint', 'sms-reminder-for-amelia' ); ?></td>
                <td><code style="font-size:11px;"><?php echo esc_html( rest_url( 'srfa/v1/sms-delivery' ) ); ?></code></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;"><?php esc_html_e( 'Table prefix', 'sms-reminder-for-amelia' ); ?></td>
                <td><code><?php global $wpdb; echo esc_html( $wpdb->prefix ); ?></code></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;"><?php esc_html_e( 'Next send cron', 'sms-reminder-for-amelia' ); ?></td>
                <td>
                    <?php
                    $next = wp_next_scheduled( 'srfa_hourly_send' );
                    echo $next ? esc_html( wp_date( 'd/m/Y H:i:s', $next ) ) : '<span style="color:#dc2626;">' . esc_html__( 'Not scheduled', 'sms-reminder-for-amelia' ) . '</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;"><?php esc_html_e( 'Next purge cron', 'sms-reminder-for-amelia' ); ?></td>
                <td>
                    <?php
                    $next_p = wp_next_scheduled( 'srfa_weekly_purge' );
                    echo $next_p ? esc_html( wp_date( 'd/m/Y H:i:s', $next_p ) ) : '<span style="color:#dc2626;">' . esc_html__( 'Not scheduled', 'sms-reminder-for-amelia' ) . '</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;"><?php esc_html_e( 'Active slots', 'sms-reminder-for-amelia' ); ?></td>
                <td>
                    <?php
                    $active = array_keys( srfa_get_active_slots() );
                    if ( empty( $active ) ) {
                        echo '<span style="color:#dc2626;">' . esc_html__( 'None', 'sms-reminder-for-amelia' ) . '</span>';
                    } else {
                        foreach ( $active as $n ) {
                            $offset_label = $offsets[ $slots[ $n ]['offset_minutes'] ] ?? '?';
                            /* translators: 1: slot number, 2: offset label (e.g. "1 hour before"). */
                            $tag = sprintf( __( 'SMS %1$d · %2$s', 'sms-reminder-for-amelia' ), (int) $n, $offset_label );
                            echo '<span style="display:inline-block;margin-right:8px;padding:2px 8px;background:#dbeafe;color:#1e40af;border-radius:10px;font-weight:600;">' . esc_html( $tag ) . '</span>';
                        }
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <?php srfa_render_branding_footer(); ?>

</div><!-- .wrap -->

<script>
// ── Toggle API key visibility ────────────────────────────────────────────────
function srfa_toggle_key(btn) {
    var input = document.getElementById('srfa_api_key');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = <?php echo wp_json_encode( __( 'Hide', 'sms-reminder-for-amelia' ) ); ?>;
    } else {
        input.type = 'password';
        btn.textContent = <?php echo wp_json_encode( __( 'Show', 'sms-reminder-for-amelia' ) ); ?>;
    }
}

// ── Visually toggle a slot ───────────────────────────────────────────────────
function srfa_toggle_slot(n, enabled) {
    var body = document.getElementById('srfa_slot_' + n + '_body');
    if (!body) return;
    if (enabled) {
        body.style.opacity = '1';
        body.style.pointerEvents = 'auto';
    } else {
        body.style.opacity = '0.45';
        body.style.pointerEvents = 'none';
    }
}

// ── Live template preview (per slot) ─────────────────────────────────────────
var srfa_vars = <?php echo wp_json_encode( $preview_vars ); ?>;

function srfa_update_preview(n, tpl) {
    var msg = tpl;
    for (var k in srfa_vars) {
        msg = msg.split(k).join(srfa_vars[k]);
    }
    var chars = msg.length;
    var segs  = Math.max(1, Math.ceil(chars / 160));

    var el = document.getElementById('srfa_preview_text_' + n);
    if (el) el.textContent = msg;

    var ec = document.getElementById('srfa_preview_chars_' + n);
    if (ec) ec.textContent = chars;

    var es = document.getElementById('srfa_preview_segs_' + n);
    if (es) {
        es.textContent = segs;
        es.style.color = segs > 1 ? '#d97706' : '#16a34a';
    }
}
</script>

<?php
// ── Helper: "Locked by wp-config.php" badge ──────────────────────────────────
function srfa_locked_badge() {
    echo '<span style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;padding:2px 10px;'
       . 'border-radius:12px;font-size:11px;font-weight:600;color:#92400e;'
       . 'background:#fef3c7;border:1px solid #f59e0b;">'
       . '🔒 ' . esc_html__( 'Locked by wp-config.php', 'sms-reminder-for-amelia' ) . '</span>';
}
