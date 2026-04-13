<?php
/**
 * Page Réglages → SMS Reminder
 *
 * Inclus par srfa_render_settings_page() — WordPress chargé, utilisateur admin.
 *
 * Réglages disponibles :
 *   - Clé API SMS Partner
 *   - Expéditeur (sender)
 *   - Mode sandbox
 *   - Template du message SMS (avec variables)
 *   - Fenêtre de rappel (heures min/max avant le RDV)
 *   - Rétention des logs (jours)
 *
 * Les champs verrouillés par un define() dans wp-config.php sont affichés
 * en lecture seule avec un badge "Verrouillé par wp-config.php".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Récupération de l'option et statut de sauvegarde ─────────────────────────
$saved    = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';
$options  = get_option( SRFA_OPTION_KEY, [] );

// Valeurs courantes (lues via le helper pour affichage cohérent)
$cur_api_key   = srfa_get_option( 'api_key' );
$cur_sender    = srfa_get_option( 'sender' );
$cur_sandbox   = (bool) srfa_get_option( 'sandbox' );
$cur_template  = srfa_get_option( 'message_template' );
$cur_purge     = (int) srfa_get_option( 'purge_days' );
$cur_h_min     = (int) srfa_get_option( 'reminder_hours_min' );
$cur_h_max     = (int) srfa_get_option( 'reminder_hours_max' );
$cur_cron_freq = (int) srfa_get_option( 'cron_frequency', 15 );

$locked_api    = srfa_is_locked( 'api_key' );
$locked_sender = srfa_is_locked( 'sender' );
$locked_sbox   = srfa_is_locked( 'sandbox' );

// ── Aperçu du template (calcul côté serveur sur données fictives) ─────────────
$preview_vars = [
    '%location_name%'          => 'Salon Paris',
    '%customer_first_name%'    => 'Sophie',
    '%customer_last_name%'     => 'Martin',
    '%customer_full_name%'     => 'Sophie Martin',
    '%employee_first_name%'    => 'Claire',
    '%employee_last_name%'     => 'Dupont',
    '%employee_full_name%'     => 'Claire Dupont',
    '%service_name%'           => 'Soin du visage',
    '%appointment_date%'       => 'lundi 14 avril',
    '%appointment_start_time%' => '14h30',
];
$preview_msg  = str_replace( array_keys( $preview_vars ), array_values( $preview_vars ), $cur_template );
$preview_len  = mb_strlen( $preview_msg );
$preview_segs = max( 1, (int) ceil( $preview_len / 160 ) );

?>
<div class="wrap" style="max-width:860px;">

    <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <span style="font-size:26px;">⚙️</span> SMS Reminder — Réglages
    </h1>
    <p style="color:#64748b;margin-top:0;margin-bottom:24px;">
        Configuration du plugin d'envoi de SMS de rappel de rendez-vous.
        <a href="<?php echo esc_url( admin_url( 'tools.php?page=srfa-logs' ) ); ?>">← Voir les logs</a>
    </p>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible" style="margin-bottom:20px;">
            <p><strong>Réglages enregistrés.</strong></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'srfa_settings_group' ); ?>

        <!-- ══ Section 1 : API SMS Partner ══════════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;display:flex;align-items:center;gap:8px;">
                🔑 API SMS Partner
            </h2>

            <!-- Clé API -->
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" style="width:220px;">
                        <label for="srfa_api_key">Clé API</label>
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
                                   placeholder="Votre clé API SMS Partner">
                            <button type="button" onclick="srfa_toggle_key(this)"
                                    style="margin-left:6px;cursor:pointer;background:none;border:1px solid #d1d5db;border-radius:4px;padding:4px 10px;font-size:12px;">
                                Afficher
                            </button>
                            <p class="description">
                                Trouvez votre clé dans le dashboard SMS Partner → Paramètres → API.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Expéditeur -->
                <tr>
                    <th scope="row">
                        <label for="srfa_sender">Expéditeur (sender)</label>
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
                                3 à 11 caractères alphanumériques, sans espaces ni accents.
                                Affiché à la place du numéro sur le téléphone du client.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Mode sandbox -->
                <tr>
                    <th scope="row">Mode sandbox</th>
                    <td>
                        <?php if ( $locked_sbox ) : ?>
                            <span style="font-weight:600;"><?php echo $cur_sandbox ? '🧪 Actif (test)' : '🚀 Inactif (production)'; ?></span>
                            <?php srfa_locked_badge(); ?>
                        <?php else : ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="srfa_sandbox"
                                       name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[sandbox]"
                                       value="1"
                                       <?php checked( $options['sandbox'] ?? true ); ?>>
                                <span>Activer le mode test (aucun SMS réellement envoyé, aucun crédit débité)</span>
                            </label>
                            <p class="description" style="margin-top:6px;">
                                ⚠️ Désactivez cette option pour passer en production.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══ Section 2 : Message SMS ══════════════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;">💬 Contenu du SMS</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="srfa_template">Template du message</label>
                    </th>
                    <td>
                        <textarea id="srfa_template"
                                  name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[message_template]"
                                  rows="4" class="large-text"
                                  style="font-family:monospace;font-size:13px;"
                                  oninput="srfa_update_preview(this.value)"
                        ><?php echo esc_textarea( $options['message_template'] ?? $cur_template ); ?></textarea>

                        <p class="description" style="margin-top:8px;">
                            <strong>Variables disponibles :</strong><br>
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
                        </p>

                        <!-- Aperçu dynamique -->
                        <div style="margin-top:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;">
                            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
                                Aperçu (données fictives)
                            </div>
                            <div id="srfa_preview_text"
                                 style="font-size:13px;color:#1e293b;line-height:1.5;word-break:break-word;">
                                <?php echo esc_html( $preview_msg ); ?>
                            </div>
                            <div style="margin-top:8px;font-size:12px;color:#64748b;">
                                <span id="srfa_preview_chars"><?php echo esc_html( $preview_len ); ?></span> caractères
                                — <span id="srfa_preview_segs"><?php echo esc_html( $preview_segs ); ?></span> segment(s) SMS
                                <span style="color:#94a3b8;">(160 car./segment)</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══ Section 3 : Fréquence & fenêtre de rappel ═══════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px;">
            <h2 style="margin-top:0;font-size:16px;">🕐 Fréquence & fenêtre de rappel</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="srfa_cron_freq">Fréquence de vérification</label>
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
                            À quelle fréquence le plugin vérifie s'il y a des SMS à envoyer.
                            <strong>Doit correspondre à votre cron Plesk</strong> (actuellement toutes les 15 min).
                            <br>⚠️ Changer cette valeur replanifie automatiquement le cron WordPress à l'enregistrement.
                        </p>
                    </td>
                </tr>
            </table>

            <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0;">

            <p style="color:#64748b;font-size:13px;margin-top:0;">
                Le cron recherche les RDV dont l'heure de début est comprise entre
                <em>maintenant + heures min</em> et <em>maintenant + heures max</em>.
                La valeur par défaut (23h → 25h) envoie les rappels environ 24h avant le RDV.
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="srfa_h_min">Heures minimum avant le RDV</label>
                    </th>
                    <td>
                        <input type="number" id="srfa_h_min"
                               name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[reminder_hours_min]"
                               value="<?php echo esc_attr( $options['reminder_hours_min'] ?? $cur_h_min ); ?>"
                               min="1" max="47" step="1" style="width:80px;">
                        <span style="margin-left:6px;color:#64748b;">heures</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="srfa_h_max">Heures maximum avant le RDV</label>
                    </th>
                    <td>
                        <input type="number" id="srfa_h_max"
                               name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[reminder_hours_max]"
                               value="<?php echo esc_attr( $options['reminder_hours_max'] ?? $cur_h_max ); ?>"
                               min="2" max="48" step="1" style="width:80px;">
                        <span style="margin-left:6px;color:#64748b;">heures</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══ Section 4 : Rétention des logs ═══════════════════════════════ -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:24px;">
            <h2 style="margin-top:0;font-size:16px;">🗄️ Rétention des logs</h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="srfa_purge">Conserver les logs pendant</label>
                    </th>
                    <td>
                        <input type="number" id="srfa_purge"
                               name="<?php echo esc_attr( SRFA_OPTION_KEY ); ?>[purge_days]"
                               value="<?php echo esc_attr( $options['purge_days'] ?? $cur_purge ); ?>"
                               min="7" max="365" step="1" style="width:80px;">
                        <span style="margin-left:6px;color:#64748b;">jours</span>
                        <p class="description">La purge automatique s'exécute chaque lundi à 3h. (7–365 jours)</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Enregistrer les réglages', 'primary large' ); ?>
    </form>

    <!-- ── Informations système ─────────────────────────────────────────── -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-top:24px;">
        <h3 style="margin-top:0;font-size:13px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">
            Informations système
        </h3>
        <table style="font-size:12px;color:#475569;border-collapse:collapse;width:100%;">
            <tr>
                <td style="padding:3px 0;width:220px;color:#94a3b8;">Version du plugin</td>
                <td><?php echo esc_html( SRFA_VERSION ); ?></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;">Endpoint DLR webhook</td>
                <td><code style="font-size:11px;"><?php echo esc_html( rest_url( 'srfa/v1/sms-delivery' ) ); ?></code></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;">Préfixe tables</td>
                <td><code><?php global $wpdb; echo esc_html( $wpdb->prefix ); ?></code></td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;">Prochain cron envoi</td>
                <td>
                    <?php
                    $next = wp_next_scheduled( 'srfa_hourly_send' );
                    echo $next ? esc_html( wp_date( 'd/m/Y à H:i:s', $next ) ) : '<span style="color:#dc2626;">Non planifié</span>';
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding:3px 0;color:#94a3b8;">Prochain cron purge</td>
                <td>
                    <?php
                    $next_p = wp_next_scheduled( 'srfa_weekly_purge' );
                    echo $next_p ? esc_html( wp_date( 'd/m/Y à H:i:s', $next_p ) ) : '<span style="color:#dc2626;">Non planifié</span>';
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <?php srfa_render_branding_footer(); ?>

</div><!-- .wrap -->

<script>
// ── Afficher/masquer la clé API ───────────────────────────────────────────────
function srfa_toggle_key(btn) {
    var input = document.getElementById('srfa_api_key');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Masquer';
    } else {
        input.type = 'password';
        btn.textContent = 'Afficher';
    }
}

// ── Aperçu live du template ───────────────────────────────────────────────────
var srfa_vars = {
    '%location_name%'          : 'Salon Paris',
    '%customer_first_name%'    : 'Sophie',
    '%customer_last_name%'     : 'Martin',
    '%customer_full_name%'     : 'Sophie Martin',
    '%employee_first_name%'    : 'Claire',
    '%employee_last_name%'     : 'Dupont',
    '%employee_full_name%'     : 'Claire Dupont',
    '%service_name%'           : 'Soin du visage',
    '%appointment_date%'       : 'lundi 14 avril',
    '%appointment_start_time%' : '14h30'
};

function srfa_update_preview(tpl) {
    var msg = tpl;
    for (var k in srfa_vars) {
        msg = msg.split(k).join(srfa_vars[k]);
    }
    var chars = msg.length;
    var segs  = Math.max(1, Math.ceil(chars / 160));

    var el = document.getElementById('srfa_preview_text');
    if (el) el.textContent = msg;

    var ec = document.getElementById('srfa_preview_chars');
    if (ec) ec.textContent = chars;

    var es = document.getElementById('srfa_preview_segs');
    if (es) {
        es.textContent = segs;
        es.style.color = segs > 1 ? '#d97706' : '#16a34a';
    }
}
</script>

<?php
// ── Helper : badge "Verrouillé par wp-config.php" ────────────────────────────
function srfa_locked_badge() {
    echo '<span style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;padding:2px 10px;'
       . 'border-radius:12px;font-size:11px;font-weight:600;color:#92400e;'
       . 'background:#fef3c7;border:1px solid #f59e0b;">'
       . '🔒 Verrouillé par wp-config.php</span>';
}
