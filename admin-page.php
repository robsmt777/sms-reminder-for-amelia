<?php
/**
 * Page d'administration : Outils → SMS Reminder Logs
 *
 * Affiche :
 *  - Le statut de configuration (clé API, mode sandbox, crons)
 *  - Un bouton pour déclencher manuellement le cron
 *  - Les 50 derniers logs SMS avec pagination simple
 *
 * Ce fichier est inclus par srfa_render_admin_page() — WordPress est
 * déjà chargé, $wpdb disponible, l'utilisateur est authentifié et admin.
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

// ── Compteurs par statut ──────────────────────────────────────────────────────
$counts = $wpdb->get_results(
    "SELECT sms_status, COUNT(*) AS cnt FROM {$log_tbl} GROUP BY sms_status",
    OBJECT_K
);

function srfa_count( $status, $counts ) {
    return isset( $counts[ $status ] ) ? (int) $counts[ $status ]->cnt : 0;
}

// ── Labels et couleurs des statuts ────────────────────────────────────────────
$status_labels = [
    'pending'   => [ 'label' => 'En attente',  'color' => '#f0ad4e', 'bg' => '#fff8ee' ],
    'delivered' => [ 'label' => 'Délivré',     'color' => '#5cb85c', 'bg' => '#f0fff0' ],
    'failed'    => [ 'label' => 'Échec',       'color' => '#d9534f', 'bg' => '#fff0f0' ],
    'skipped'   => [ 'label' => 'Ignoré',      'color' => '#999',    'bg' => '#f8f8f8' ],
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

// ── Vérification de la configuration ─────────────────────────────────────────
$api_key_ok     = ! empty( srfa_get_option( 'api_key' ) );
$sandbox_on     = (bool) srfa_get_option( 'sandbox' );
$cron_next      = wp_next_scheduled( 'srfa_hourly_send' );
$cron_purge     = wp_next_scheduled( 'srfa_weekly_purge' );
$settings_url   = admin_url( 'options-general.php?page=srfa-settings' );

?>
<div class="wrap" style="max-width:1200px;">

    <h1 style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:28px;">💬</span> SMS Reminder — Logs des rappels
        <a href="<?php echo esc_url( $settings_url ); ?>"
           class="button button-secondary" style="margin-left:auto;font-size:13px;">
            ⚙️ Réglages
        </a>
    </h1>

    <?php if ( ! empty( $_GET['srfa_ran'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Cron exécuté manuellement.</strong> Consultez les logs ci-dessous pour voir les résultats.</p>
        </div>
    <?php endif; ?>

    <?php if ( ! $api_key_ok ) : ?>
        <div class="notice notice-error">
            <p><strong>⚠️ Clé API manquante !</strong>
            Renseignez-la dans <a href="<?php echo esc_url( $settings_url ); ?>">Réglages → SMS Reminder</a>
            ou via <code>define( 'SRFA_API_KEY', 'votre_cle' );</code> dans <code>wp-config.php</code>.</p>
        </div>
    <?php endif; ?>

    <?php if ( $sandbox_on ) : ?>
        <div class="notice notice-warning">
            <p><strong>🧪 Mode sandbox actif.</strong>
            Les SMS ne sont pas réellement envoyés.
            <a href="<?php echo esc_url( $settings_url ); ?>">Désactivez-le dans les Réglages</a> pour passer en production.</p>
        </div>
    <?php endif; ?>

    <!-- ── Bloc de configuration ─────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:20px 0;">

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Clé API</div>
            <?php if ( $api_key_ok ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ Configurée</span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">
                    Sender : <?php echo esc_html( srfa_get_option( 'sender' ) ); ?>
                </div>
                <?php if ( srfa_is_locked( 'api_key' ) ) : ?>
                    <div style="margin-top:6px;font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:10px;display:inline-block;">🔒 wp-config.php</div>
                <?php else : ?>
                    <div style="margin-top:6px;font-size:11px;color:#1d4ed8;background:#eff6ff;padding:2px 8px;border-radius:10px;display:inline-block;">⚙️ Dashboard</div>
                <?php endif; ?>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ Manquante</span>
                <div style="margin-top:6px;">
                    <a href="<?php echo esc_url( $settings_url ); ?>" style="font-size:12px;">→ Configurer</a>
                </div>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Mode</div>
            <?php if ( $sandbox_on ) : ?>
                <span style="color:#d97706;font-weight:600;">🧪 Sandbox</span>
            <?php else : ?>
                <span style="color:#16a34a;font-weight:600;">🚀 Production</span>
            <?php endif; ?>
            <?php if ( srfa_is_locked( 'sandbox' ) ) : ?>
                <div style="margin-top:6px;font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:10px;display:inline-block;">🔒 wp-config.php</div>
            <?php else : ?>
                <div style="margin-top:6px;font-size:11px;color:#1d4ed8;background:#eff6ff;padding:2px 8px;border-radius:10px;display:inline-block;">⚙️ Dashboard</div>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Cron — envoi</div>
            <?php if ( $cron_next ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ Actif</span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">Prochain : <?php echo esc_html( wp_date( 'd/m/Y H:i', $cron_next ) ); ?></div>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ Inactif</span>
            <?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Cron — purge</div>
            <?php if ( $cron_purge ) : ?>
                <span style="color:#16a34a;font-weight:600;">✓ Actif</span>
                <div style="font-size:12px;color:#64748b;margin-top:4px;">Prochain : <?php echo esc_html( wp_date( 'd/m/Y H:i', $cron_purge ) ); ?></div>
            <?php else : ?>
                <span style="color:#dc2626;font-weight:600;">✗ Inactif</span>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Statistiques rapides ───────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px;">
        <?php
        $stat_items = [
            [ 'label' => 'Total',      'value' => $total_rows,                                  'color' => '#1e293b' ],
            [ 'label' => 'En attente', 'value' => srfa_count( 'pending',   $counts ),       'color' => '#d97706' ],
            [ 'label' => 'Délivrés',   'value' => srfa_count( 'delivered', $counts ),       'color' => '#16a34a' ],
            [ 'label' => 'Échecs',     'value' => srfa_count( 'failed',    $counts ),       'color' => '#dc2626' ],
            [ 'label' => 'Ignorés',    'value' => srfa_count( 'skipped',   $counts ),       'color' => '#64748b' ],
        ];
        foreach ( $stat_items as $s ) :
        ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $s['color'] ); ?>;"><?php echo esc_html( $s['value'] ); ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;"><?php echo esc_html( $s['label'] ); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Actions manuelles ─────────────────────────────────────────────── -->
    <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'srfa_run_now' ); ?>
            <input type="hidden" name="action" value="srfa_run_now">
            <button type="submit" class="button button-primary"
                    onclick="return confirm('Lancer le traitement des rappels SMS maintenant ?')">
                ▶ Lancer le cron maintenant
            </button>
        </form>

        <div style="font-size:12px;color:#64748b;">
            Endpoint DLR :
            <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px;">
                <?php echo esc_html( rest_url( 'srfa/v1/sms-delivery' ) ); ?>
            </code>
        </div>
    </div>

    <!-- ── Tableau des logs ───────────────────────────────────────────────── -->
    <?php if ( empty( $logs ) ) : ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:40px;text-align:center;color:#64748b;">
            <div style="font-size:40px;margin-bottom:12px;">📭</div>
            <p>Aucun log pour l'instant. Le cron enverra les premiers rappels dans l'heure.</p>
        </div>
    <?php else : ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <table class="wp-list-table widefat fixed striped" style="border:none;">
                <thead>
                    <tr>
                        <th style="width:140px;">Date RDV</th>
                        <th style="width:60px;text-align:center;" title="Slot de rappel (SMS 1 = principal, SMS 2 = complémentaire)">Slot</th>
                        <th style="width:160px;">Client</th>
                        <th style="width:120px;">Téléphone</th>
                        <th>Service</th>
                        <th style="width:130px;">Employée</th>
                        <th style="width:100px;">Statut SMS</th>
                        <th style="width:130px;">Envoyé le</th>
                        <th style="width:130px;">Délivré le</th>
                        <th style="width:40px;text-align:center;" title="Détails">⋯</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ) :
                        // appointment_datetime est stocké en UTC (comme Amelia) — conversion vers l'heure locale WP pour affichage
                        $appt_dt  = date_i18n( 'd/m/Y H:i', strtotime( get_date_from_gmt( $log->appointment_datetime ) ) );
                        $sent_dt  = $log->sent_at    ? date_i18n( 'd/m/Y H:i', strtotime( $log->sent_at ) )     : '—';
                        $deliv_dt = $log->delivery_at ? date_i18n( 'd/m/Y H:i', strtotime( $log->delivery_at ) ) : '—';
                    ?>
                    <?php
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
                        <td><?php echo esc_html( $log->customer_name ); ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?php echo esc_html( $log->customer_phone ); ?></td>
                        <td><?php echo esc_html( $log->service_name ); ?></td>
                        <td><?php echo esc_html( $log->employee_name ); ?></td>
                        <td><?php echo srfa_status_badge( $log->sms_status, $status_labels ); ?></td>
                        <td style="font-size:12px;color:#64748b;"><?php echo esc_html( $sent_dt ); ?></td>
                        <td style="font-size:12px;color:#64748b;"><?php echo esc_html( $deliv_dt ); ?></td>
                        <td style="text-align:center;">
                            <?php if ( $log->error_message || $log->sms_message_id ) : ?>
                            <span title="<?php echo esc_attr( $log->error_message ?: 'ID : ' . $log->sms_message_id ); ?>"
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
                Page <?php echo esc_html( $current_page ); ?> / <?php echo esc_html( $total_pages ); ?>
                (<?php echo esc_html( $total_rows ); ?> entrées)
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
        Les logs sont purgés automatiquement après <?php echo esc_html( srfa_get_option( 'purge_days', 30 ) ); ?> jours.
        Préfixe de table détecté : <code><?php echo esc_html( $wpdb->prefix ); ?></code> —
        <a href="<?php echo esc_url( $settings_url ); ?>" style="color:#94a3b8;">Modifier les réglages</a>
    </p>

    <?php srfa_render_branding_footer(); ?>

</div><!-- .wrap -->
