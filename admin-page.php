<?php
/**
 * Admin page: Tools → SMS Reminder Logs
 *
 * Displays:
 *  - Configuration status (API key, sandbox mode, crons)
 *  - Manual "run now" button
 *  - Last 50 SMS logs with simple pagination
 *
 * Included by srfa_render_logs_page() — WordPress already loaded,
 * $wpdb available, user authenticated as admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$log_tbl = $wpdb->prefix . SRFA_LOG_TABLE;

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page    = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$offset      = ( $current_page - 1 ) * $per_page;

$total_rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_tbl}" );
$total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );

$logs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$log_tbl} ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
) );

// ── Counters by status ────────────────────────────────────────────────────────
$counts = $wpdb->get_results(
    "SELECT sms_status, COUNT(*) AS cnt FROM {$log_tbl} GROUP BY sms_status",
    OBJECT_K
);

function srfa_count( $status, $counts ) {
    return isset( $counts[ $status ] ) ? (int) $counts[ $status ]->cnt : 0;
}

// ── Status labels & colors ────────────────────────────────────────────────────
$status_labels = [
    'pending'   => [ 'label' => __( 'Pending',   'sms-reminder-for-amelia' ), 'color' => '#f0ad4e', 'bg' => '#fff8ee' ],
    'delivered' => [ 'label' => __( 'Delivered', 'sms-reminder-for-amelia' ), 'color' => '#5cb85c', 'bg' => '#f0fff0' ],
    'failed'    => [ 'label' => __( 'Failed',    'sms-reminder-for-amelia' ), 'color' => '#d9534f', 'bg' => '#fff0f0' ],
    'skipped'   => [ 'label' => __( 'Skipped',   'sms-reminder-for-amelia' ), 'color' => '#999',    'bg' => '#f8f8f8' ],
];

function srfa_status_badge( $status, $labels ) {
    $info  = isset( $labels[ $status ] ) ? $labels[ $status ] : [ 'label' => $status, 'color' => '#999', 'bg' => '#eee' ];
    $style = sprintf(
        'display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;color:%s;background:%s;border:1px solid %s;',
        esc_attr( $info['color'] ),
        esc_attr( $info['bg'] ),
        esc_attr( $info['color'] )
    );
    return '<span style="' . $style . '">' . esc_html( $info['label'] ) . '</span>';
}

// ── Config check ─────────────────────────────────────────────────────────────
$active_gateway_obj = SRFA_Gateway_Registry::active();
$active_gw_id       = $active_gateway_obj->get_id();
$gw_settings        = srfa_get_active_gateway_settings();

// Heuristic: a gateway is "configured" if every non-checkbox, non-default
// mandatory field has a non-empty value. We simplify: check that at least
// one credential-like field (password or non-placeholder) is set.
$api_configured = false;
foreach ( $active_gateway_obj->get_fields() as $f ) {
    if ( in_array( $f['type'] ?? 'text', [ 'password', 'text' ], true ) ) {
        if ( ! empty( $gw_settings[ $f['key'] ] ) ) {
            $api_configured = true;
            break;
        }
    }
}
// Sandbox concept only exists for SMSPartner & Twilio has test creds; keep simple
$sandbox_on     = ! empty( $gw_settings['sandbox'] );
$cron_next      = wp_next_scheduled( 'srfa_hourly_send' );
$cron_purge     = wp_next_scheduled( 'srfa_weekly_purge' );
$settings_url   = admin_url( 'options-general.php?page=srfa-settings' );

?>
<div class="wrap" style="max-width:1200px;">

    <h1 style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:28px;">💬</span> <?php esc_html_e( 'SMS Reminder — Reminder logs', 'sms-reminder-for-amelia' ); ?>
        <a href="<?php echo esc_url( $settings_url ); ?>"
           class="button button-secondary" style="margin-left:auto;font-size:13px;">
            ⚙️ <?php esc_html_e( 'Settings', 'sms-reminder-for-amelia' ); ?>
        </a>
    </h1>

    <?php if ( ! empty( $_GET['srfa_ran'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php esc_html_e( 'Cron executed manually.', 'sms-reminder-for-amelia' ); ?></strong>
            <?php esc_html_e( 'See the logs below for results.', 'sms-reminder-for-amelia' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! $api_configured ) : ?>
        <div class="notice notice-error">
            <p><strong>⚠️ <?php esc_html_e( 'Gateway not configured!', 'sms-reminder-for-amelia' ); ?></strong>
            <?php
            printf(
                /* translators: 1: gateway label, 2: Settings page hyperlink. */
                esc_html__( 'The active gateway (%1$s) has missing credentials. Configure it in %2$s.', 'sms-reminder-for-amelia' ),
                '<strong>' . esc_html( $active_gateway_obj->get_label() ) . '</strong>',
                '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings → SMS Reminder', 'sms-reminder-for-amelia' ) . '</a>'
            );
            ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $sandbox_on ) : ?>
        <div class="notice notice-warning">
            <p><strong>🧪 <?php esc_html_e( 'Sandbox mode is active.', 'sms-reminder-for-amelia' ); ?></strong>
            <?php esc_html_e( 'SMS messages are not actually sent.', 'sms-reminder-for-amelia' ); ?>
            <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Disable it in Settings', 'sms-reminder-for-amelia' ); ?></a>
            <?php esc_html_e( 'to switch to production.', 'sms-reminder-for-amelia' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- ── Configuration tiles ────────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:20px 0;">

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Active gateway', 'sms-reminder-for-amelia' ); ?></div>
            <?php if ( $api_configured ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ <?php echo esc_html( $active_gateway_obj->get_label() ); ?></span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                    <?php esc_html_e( 'Configured', 'sms-reminder-for-amelia' ); ?>
                </div>
                <?php if ( $active_gw_id === 'smspartner' && srfa_is_locked_smspartner( 'api_key' ) ) : ?>
                    <div style="margin-top:6px;font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:10px;display:inline-block;">🔒 wp-config.php</div>
                <?php else : ?>
                    <div style="margin-top:6px;font-size:11px;color:#1d4ed8;background:#eff6ff;padding:2px 8px;border-radius:10px;display:inline-block;">⚙️ <?php esc_html_e( 'Dashboard', 'sms-reminder-for-amelia' ); ?></div>
                <?php endif; ?>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ <?php echo esc_html( $active_gateway_obj->get_label() ); ?></span>
                <div style="margin-top:6px;">
                    <a href="<?php echo esc_url( $settings_url ); ?>" style="font-size:12px;">→ <?php esc_html_e( 'Configure', 'sms-reminder-for-amelia' ); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Mode', 'sms-reminder-for-amelia' ); ?></div>
            <?php if ( $sandbox_on ) : ?>
                <span style="color:#d97706;font-weight:600;">🧪 <?php esc_html_e( 'Sandbox', 'sms-reminder-for-amelia' ); ?></span>
            <?php else : ?>
                <span style="color:#16a34a;font-weight:600;">🚀 <?php esc_html_e( 'Production', 'sms-reminder-for-amelia' ); ?></span>
            <?php endif; ?>
            <?php if ( $active_gw_id === 'smspartner' && srfa_is_locked_smspartner( 'sandbox' ) ) : ?>
                <div style="margin-top:6px;font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:10px;display:inline-block;">🔒 wp-config.php</div>
            <?php else : ?>
                <div style="margin-top:6px;font-size:11px;color:#1d4ed8;background:#eff6ff;padding:2px 8px;border-radius:10px;display:inline-block;">⚙️ <?php esc_html_e( 'Dashboard', 'sms-reminder-for-amelia' ); ?></div>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Cron — sending', 'sms-reminder-for-amelia' ); ?></div>
            <?php if ( $cron_next ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ <?php esc_html_e( 'Active', 'sms-reminder-for-amelia' ); ?></span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Next:', 'sms-reminder-for-amelia' ); ?> <?php echo esc_html( wp_date( 'd/m/Y H:i', $cron_next ) ); ?></div>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ <?php esc_html_e( 'Inactive', 'sms-reminder-for-amelia' ); ?></span>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?php esc_html_e( 'Cron — purge', 'sms-reminder-for-amelia' ); ?></div>
            <?php if ( $cron_purge ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ <?php esc_html_e( 'Active', 'sms-reminder-for-amelia' ); ?></span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Next:', 'sms-reminder-for-amelia' ); ?> <?php echo esc_html( wp_date( 'd/m/Y H:i', $cron_purge ) ); ?></div>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ <?php esc_html_e( 'Inactive', 'sms-reminder-for-amelia' ); ?></span>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Quick stats ──────────────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px;">
        <?php
        $stat_items = [
            [ 'label' => __( 'Total',     'sms-reminder-for-amelia' ), 'value' => $total_rows,                             'color' => '#1e293b' ],
            [ 'label' => __( 'Pending',   'sms-reminder-for-amelia' ), 'value' => srfa_count( 'pending',   $counts ),  'color' => '#d97706' ],
            [ 'label' => __( 'Delivered', 'sms-reminder-for-amelia' ), 'value' => srfa_count( 'delivered', $counts ),  'color' => '#16a34a' ],
            [ 'label' => __( 'Failed',    'sms-reminder-for-amelia' ), 'value' => srfa_count( 'failed',    $counts ),  'color' => '#dc2626' ],
            [ 'label' => __( 'Skipped',   'sms-reminder-for-amelia' ), 'value' => srfa_count( 'skipped',   $counts ),  'color' => '#64748b' ],
        ];
        foreach ( $stat_items as $s ) :
        ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $s['color'] ); ?>;"><?php echo esc_html( $s['value'] ); ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;"><?php echo esc_html( $s['label'] ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Manual actions ───────────────────────────────────────────────── -->
    <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'srfa_run_now' ); ?>
            <input type="hidden" name="action" value="srfa_run_now">
            <button type="submit" class="button button-primary"
                    onclick="return confirm('<?php echo esc_js( __( 'Run SMS reminder processing now?', 'sms-reminder-for-amelia' ) ); ?>')">
                ▶ <?php esc_html_e( 'Run cron now', 'sms-reminder-for-amelia' ); ?>
            </button>
        </form>

        <div style="font-size:12px;color:#64748b;">
            <?php esc_html_e( 'DLR endpoint:', 'sms-reminder-for-amelia' ); ?>
            <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;">
                <?php echo esc_html( rest_url( 'srfa/v1/sms-delivery/' . srfa_get_option( 'active_gateway', 'smspartner' ) ) ); ?>
            </code>
        </div>
    </div>

    <!-- ── Logs table ───────────────────────────────────────────────────── -->
    <?php if ( empty( $logs ) ) : ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:40px;text-align:center;color:#64748b;">
            <div style="font-size:40px;margin-bottom:12px;">📭</div>
            <p><?php esc_html_e( 'No logs yet. The cron will send the first reminders within the hour.', 'sms-reminder-for-amelia' ); ?></p>
        </div>
    <?php else : ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <table class="wp-list-table widefat fixed striped" style="border:none;">
                <thead>
                    <tr>
                        <th style="width:140px;"><?php esc_html_e( 'Appointment date', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:60px;text-align:center;" title="<?php echo esc_attr__( 'Reminder slot (SMS 1 = primary, SMS 2 = secondary)', 'sms-reminder-for-amelia' ); ?>"><?php esc_html_e( 'Slot', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Gateway', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'Customer', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:120px;"><?php esc_html_e( 'Phone', 'sms-reminder-for-amelia' ); ?></th>
                        <th><?php esc_html_e( 'Service', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:130px;"><?php esc_html_e( 'Employee', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'SMS status', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:130px;"><?php esc_html_e( 'Sent at', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:130px;"><?php esc_html_e( 'Delivered at', 'sms-reminder-for-amelia' ); ?></th>
                        <th style="width:40px;text-align:center;" title="<?php echo esc_attr__( 'Details', 'sms-reminder-for-amelia' ); ?>">⋯</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) :
                        $appt_dt  = date_i18n( 'd/m/Y H:i', strtotime( get_date_from_gmt( $log->appointment_datetime ) ) );
                        $sent_dt  = $log->sent_at    ? date_i18n( 'd/m/Y H:i', strtotime( $log->sent_at ) )     : '—';
                        $deliv_dt = $log->delivery_at ? date_i18n( 'd/m/Y H:i', strtotime( $log->delivery_at ) ) : '—';
                        $slot_num = isset( $log->reminder_slot ) ? (int) $log->reminder_slot : 1;
                        $slot_bg  = $slot_num === 1 ? '#dbeafe' : '#fae8ff';
                        $slot_fg  = $slot_num === 1 ? '#1e40af' : '#86198f';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $appt_dt ); ?></strong></td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;min-width:28px;padding:2px 8px;background:<?php echo esc_attr( $slot_bg ); ?>;color:<?php echo esc_attr( $slot_fg ); ?>;border-radius:10px;font-size:11px;font-weight:700;">
                                <?php echo (int) $slot_num; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $log_gw   = isset( $log->gateway ) && $log->gateway ? (string) $log->gateway : 'smspartner';
                            $gw_obj   = SRFA_Gateway_Registry::get( $log_gw );
                            $gw_label = $gw_obj ? $gw_obj->get_label() : $log_gw;
                            ?>
                            <span style="display:inline-block;padding:2px 8px;background:#f1f5f9;color:#334155;border-radius:10px;font-size:11px;font-weight:600;">
                                <?php echo esc_html( $gw_label ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log->customer_name ); ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?php echo esc_html( $log->customer_phone ); ?></td>
                        <td><?php echo esc_html( $log->service_name ); ?></td>
                        <td><?php echo esc_html( $log->employee_name ); ?></td>
                        <td><?php echo srfa_status_badge( $log->sms_status, $status_labels ); ?></td>
                        <td style="font-size:12px;color:#64748b;"><?php echo esc_html( $sent_dt ); ?></td>
                        <td style="font-size:12px;color:#64748b;"><?php echo esc_html( $deliv_dt ); ?></td>
                        <td style="text-align:center;">
                            <?php if ( $log->error_message || $log->sms_message_id ) : ?>
                            <span title="<?php echo esc_attr( $log->error_message ?: __( 'ID:', 'sms-reminder-for-amelia' ) . ' ' . $log->sms_message_id ); ?>"
                                  style="cursor:help;color:#64748b;">ℹ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
        <div style="margin-top:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="color:#64748b;font-size:13px;">
                <?php
                printf(
                    /* translators: 1: current page, 2: total pages, 3: total entries. */
                    esc_html__( 'Page %1$d / %2$d (%3$d entries)', 'sms-reminder-for-amelia' ),
                    (int) $current_page, (int) $total_pages, (int) $total_rows
                );
                ?>
            </span>
            <div style="margin-left:auto;display:flex;gap:4px;">
                <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                    $url   = add_query_arg( [ 'page' => 'srfa-logs', 'paged' => $p ], admin_url( 'tools.php' ) );
                    $style = $p === $current_page
                        ? 'background:#2563eb;color:#fff;border-color:#2563eb;'
                        : 'background:#fff;color:#374151;';
                ?>
                    <a href="<?php echo esc_url( $url ); ?>"
                       style="<?php echo esc_attr( $style ); ?>padding:4px 10px;border:1px solid #d1d5db;border-radius:4px;text-decoration:none;font-size:13px;">
                        <?php echo esc_html( $p ); ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; // empty logs ?>

    <p style="margin-top:24px;font-size:12px;color:#94a3b8;">
        <?php
        printf(
            /* translators: 1: retention days, 2: table prefix code, 3: settings link. */
            esc_html__( 'Logs are purged automatically after %1$d days. Detected table prefix: %2$s — %3$s', 'sms-reminder-for-amelia' ),
            (int) srfa_get_option( 'purge_days', 30 ),
            '<code>' . esc_html( $wpdb->prefix ) . '</code>',
            '<a href="' . esc_url( $settings_url ) . '" style="color:#94a3b8;">' . esc_html__( 'Edit settings', 'sms-reminder-for-amelia' ) . '</a>'
        );
        ?>
    </p>

    <?php srfa_render_branding_footer(); ?>

</div><!-- .wrap -->
