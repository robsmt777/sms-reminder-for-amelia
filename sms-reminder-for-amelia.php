<?php
/**
 * Plugin Name: SMS Reminder for Amelia
 * Plugin URI:  https://capitainesite.com/
 * Description: Envoi automatique de SMS de rappel de rendez-vous pour Amelia Booking, via SMS Partner, en lisant directement les tables Amelia. D'autres passerelles SMS arriveront dans les prochaines versions.
 * Version:     1.0.0
 * Author:      Capitaine Site — Agence experte WordPress
 * Author URI:  https://capitainesite.com/
 * License:     GPL-2.0-or-later
 * Text Domain: sms-reminder-for-amelia
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CONFIGURATION
 *
 * Tous les réglages se configurent dans l'interface WordPress :
 *   Réglages → SMS Reminder
 *
 * Les define() dans wp-config.php restent prioritaires s'ils existent
 * (utile pour des environnements multi-serveurs ou déploiements automatisés) :
 *
 *   define( 'SRFA_API_KEY',  'ta_cle_api_smspartner' );
 *   define( 'SRFA_SANDBOX',  false );
 *   define( 'SRFA_SENDER',   'Reminder' );
 * ─────────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constantes internes (non surchargeables) ────────────────────────────────

define( 'SRFA_VERSION',    '1.0.0' );
define( 'SRFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRFA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRFA_API_URL',    'https://api.smspartner.fr/v1/send' );
define( 'SRFA_LOG_TABLE',  'srfa_logs' );    // sans préfixe $wpdb->prefix
define( 'SRFA_OPTION_KEY', 'srfa_settings' );


// ─── Helper : lecture d'une option (define > BDD) ────────────────────────────

/**
 * Retourne la valeur effective d'un réglage.
 * Priorité : define() dans wp-config.php > option en base > valeur par défaut.
 *
 * @param  string $key     'api_key' | 'sandbox' | 'sender' | 'message_template' | 'purge_days' | 'reminder_hours_min' | 'reminder_hours_max'
 * @param  mixed  $default Valeur si ni define ni option.
 * @return mixed
 */
function srfa_get_option( $key, $default = null ) {
    // Mapping clé → constante PHP
    $const_map = [
        'api_key' => 'SRFA_API_KEY',
        'sandbox' => 'SRFA_SANDBOX',
        'sender'  => 'SRFA_SENDER',
    ];

    if ( isset( $const_map[ $key ] ) && defined( $const_map[ $key ] ) ) {
        $val = constant( $const_map[ $key ] );
        // On considère que le define est actif seulement s'il n'est pas la valeur par défaut vide
        if ( $key !== 'api_key' || $val !== '' ) {
            return $val;
        }
    }

    $options = get_option( SRFA_OPTION_KEY, [] );

    if ( isset( $options[ $key ] ) && $options[ $key ] !== '' ) {
        return $options[ $key ];
    }

    // Valeurs par défaut
    $defaults = [
        'api_key'             => '',
        'sandbox'             => true,
        'sender'              => 'Reminder',
        'message_template'    => "%location_name% : Bonjour %customer_full_name%, RDV demain à %appointment_start_time% avec %employee_first_name% pour %service_name%. Merci de prévenir si annulation.",
        'purge_days'          => 30,
        'reminder_hours_min'  => 23,
        'reminder_hours_max'  => 25,
        'cron_frequency'      => 15,
    ];

    if ( $default !== null ) {
        return $default;
    }

    return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
}

/**
 * Indique si un réglage est verrouillé par un define() wp-config.php.
 * Utilisé pour afficher un badge "verrouillé" dans l'UI.
 *
 * @param  string $key
 * @return bool
 */
function srfa_is_locked( $key ) {
    $const_map = [
        'api_key' => 'SRFA_API_KEY',
        'sandbox' => 'SRFA_SANDBOX',
        'sender'  => 'SRFA_SENDER',
    ];
    if ( ! isset( $const_map[ $key ] ) ) {
        return false;
    }
    $const = $const_map[ $key ];
    if ( ! defined( $const ) ) {
        return false;
    }
    // api_key vide = pas vraiment verrouillé
    if ( $key === 'api_key' && constant( $const ) === '' ) {
        return false;
    }
    return true;
}


// ─── Activation / Désactivation / Désinstallation ────────────────────────────

register_activation_hook( __FILE__,   'srfa_activate' );
register_deactivation_hook( __FILE__, 'srfa_deactivate' );

function srfa_activate() {
    srfa_create_table();
    // Purge l'ancien cron (qui était peut-être en 'hourly') avant de réenregistrer en 15 min
    srfa_clear_crons();
    srfa_register_crons();
    error_log( '[SMS Reminder] Plugin activé (v' . SRFA_VERSION . ') — table et crons initialisés.' );
}

function srfa_deactivate() {
    srfa_clear_crons();
    error_log( '[SMS Reminder] Plugin désactivé — crons supprimés, logs et réglages conservés.' );
}


// ─── Création de la table ─────────────────────────────────────────────────────

function srfa_create_table() {
    global $wpdb;

    $table   = $wpdb->prefix . SRFA_LOG_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        appointment_id       BIGINT(20) UNSIGNED NOT NULL,
        customer_phone       VARCHAR(63)         NOT NULL DEFAULT '',
        customer_name        VARCHAR(255)        NOT NULL DEFAULT '',
        appointment_datetime DATETIME            NOT NULL,
        service_name         VARCHAR(255)        NOT NULL DEFAULT '',
        employee_name        VARCHAR(255)        NOT NULL DEFAULT '',
        location_name        VARCHAR(255)        NOT NULL DEFAULT '',
        sms_status           ENUM('pending','delivered','failed','skipped') NOT NULL DEFAULT 'pending',
        sms_message_id       VARCHAR(64)         DEFAULT NULL,
        error_message        TEXT                DEFAULT NULL,
        sent_at              DATETIME            DEFAULT NULL,
        delivery_at          DATETIME            DEFAULT NULL,
        created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_appointment_id (appointment_id),
        KEY idx_sms_status     (sms_status),
        KEY idx_created_at     (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}


// ─── Intervalles cron custom ──────────────────────────────────────────────────

/**
 * Retourne la liste des fréquences disponibles (en minutes).
 * Clé = valeur stockée en BDD, valeur = libellé.
 */
function srfa_cron_frequencies() {
    return [
        1  => 'Toutes les 1 minute',
        5  => 'Toutes les 5 minutes',
        10 => 'Toutes les 10 minutes',
        15 => 'Toutes les 15 minutes',
        30 => 'Toutes les 30 minutes',
        60 => 'Toutes les heures',
    ];
}

add_filter( 'cron_schedules', function ( $schedules ) {
    foreach ( srfa_cron_frequencies() as $minutes => $label ) {
        $key = 'srfa_every_' . $minutes . 'min';
        if ( ! isset( $schedules[ $key ] ) ) {
            $schedules[ $key ] = [
                'interval' => $minutes * MINUTE_IN_SECONDS,
                'display'  => $label,
            ];
        }
    }
    return $schedules;
} );

/**
 * Retourne le slug WP-Cron correspondant à la fréquence configurée.
 */
function srfa_get_cron_schedule() {
    $freq = (int) srfa_get_option( 'cron_frequency', 15 );
    return 'srfa_every_' . $freq . 'min';
}


// ─── Gestion des crons ────────────────────────────────────────────────────────

function srfa_register_crons() {
    if ( ! wp_next_scheduled( 'srfa_hourly_send' ) ) {
        wp_schedule_event( time(), srfa_get_cron_schedule(), 'srfa_hourly_send' );
    }
    if ( ! wp_next_scheduled( 'srfa_weekly_purge' ) ) {
        $next_monday = strtotime( 'next monday 03:00' );
        wp_schedule_event( $next_monday, 'weekly', 'srfa_weekly_purge' );
    }
}

function srfa_clear_crons() {
    foreach ( [ 'srfa_hourly_send', 'srfa_weekly_purge' ] as $hook ) {
        $ts = wp_next_scheduled( $hook );
        if ( $ts ) { wp_unschedule_event( $ts, $hook ); }
    }
}

add_action( 'srfa_hourly_send',  'srfa_process_reminders' );
add_action( 'srfa_weekly_purge', 'srfa_purge_old_logs' );


// ─── Traitement principal : envoi des rappels ─────────────────────────────────

function srfa_process_reminders() {
    global $wpdb;

    error_log( '[SMS Reminder] Démarrage du cron horaire.' );

    $api_key = srfa_get_option( 'api_key' );
    if ( empty( $api_key ) ) {
        error_log( '[SMS Reminder] ERREUR : clé API non configurée (ni define ni réglage dashboard).' );
        return;
    }

    $prefix  = $wpdb->prefix;
    $log_tbl = $prefix . SRFA_LOG_TABLE;

    $hours_min = (int) srfa_get_option( 'reminder_hours_min', 23 );
    $hours_max = (int) srfa_get_option( 'reminder_hours_max', 25 );

    // Amelia stocke bookingStart en UTC (DateTimeService::setTimezone('UTC')),
    // donc la fenêtre de comparaison doit être construite en UTC également.
    $from = gmdate( 'Y-m-d H:i:s', time() + ( $hours_min * HOUR_IN_SECONDS ) );
    $to   = gmdate( 'Y-m-d H:i:s', time() + ( $hours_max * HOUR_IN_SECONDS ) );

    $sql = $wpdb->prepare(
        "SELECT
            appt.id                                         AS appointment_id,
            appt.bookingStart                               AS appointment_datetime,
            CONCAT(cust.firstName, ' ', cust.lastName)      AS customer_name,
            cust.firstName                                  AS customer_first_name,
            cust.phone                                      AS customer_phone,
            CONCAT(prov.firstName, ' ', prov.lastName)      AS employee_name,
            svc.name                                        AS service_name,
            COALESCE(loc.name, '')                          AS location_name
        FROM      {$prefix}amelia_appointments      AS appt
        JOIN      {$prefix}amelia_customer_bookings AS booking
                  ON booking.appointmentId = appt.id
                  AND booking.status = 'approved'
        JOIN      {$prefix}amelia_users             AS cust
                  ON cust.id = booking.customerId
                  AND cust.type = 'customer'
        JOIN      {$prefix}amelia_users             AS prov
                  ON prov.id = appt.providerId
                  AND prov.type = 'provider'
        JOIN      {$prefix}amelia_services          AS svc
                  ON svc.id = appt.serviceId
        LEFT JOIN {$prefix}amelia_locations         AS loc
                  ON loc.id = appt.locationId
        WHERE appt.status        = 'approved'
          AND appt.bookingStart  BETWEEN %s AND %s
          AND cust.phone        != ''
          AND cust.phone        IS NOT NULL
          AND NOT EXISTS (
                SELECT 1
                FROM   {$log_tbl} AS l
                WHERE  l.appointment_id       = appt.id
                  AND  l.sms_status           IN ('pending', 'delivered')
                  AND  l.appointment_datetime = appt.bookingStart
          )
        GROUP BY appt.id",
        $from,
        $to
    );

    $appointments = $wpdb->get_results( $sql );

    if ( empty( $appointments ) ) {
        error_log( "[SMS Reminder] Aucun RDV à rappeler dans la fenêtre {$from} → {$to}." );
        return;
    }

    error_log( sprintf( '[SMS Reminder] %d RDV trouvés à traiter.', count( $appointments ) ) );

    foreach ( $appointments as $appt ) {
        srfa_send_reminder( $appt );
    }
}


// ─── Envoi d'un SMS individuel ────────────────────────────────────────────────

function srfa_format_phone( $phone ) {
    $phone = preg_replace( '/[\s\-\.\(\)]/', '', $phone );

    if ( empty( $phone ) ) { return false; }

    if ( preg_match( '/^\+\d{7,15}$/', $phone ) )         { return $phone; }
    if ( preg_match( '/^0033(\d{9})$/', $phone, $m ) )    { return '+33' . $m[1]; }
    if ( preg_match( '/^0([67]\d{8})$/', $phone, $m ) )   { return '+33' . $m[1]; }
    if ( preg_match( '/^33(\d{9})$/', $phone, $m ) )      { return '+33' . $m[1]; }
    if ( preg_match( '/^\d{9}$/', $phone ) )               { return '+33' . $phone; }

    error_log( "[SMS Reminder] Numéro non reconnu, ignoré : {$phone}" );
    return false;
}


/**
 * Construit le message SMS à partir du template configurable.
 *
 * Variables disponibles (format Amelia) :
 *   %location_name%          Nom du lieu
 *   %customer_first_name%    Prénom du client
 *   %customer_last_name%     Nom du client
 *   %customer_full_name%     Prénom + Nom du client
 *   %employee_first_name%    Prénom de l'employée
 *   %employee_last_name%     Nom de l'employée
 *   %employee_full_name%     Prénom + Nom de l'employée
 *   %service_name%           Nom du service
 *   %appointment_date%       Date du RDV (ex : lundi 14 avril)
 *   %appointment_start_time% Heure de début (ex : 14h30)
 */
function srfa_build_message( $appt ) {
    $dt         = get_date_from_gmt( $appt->appointment_datetime );
    $time_str   = date( 'H\hi', strtotime( $dt ) );
    $date_str   = date_i18n( 'l j F', strtotime( $dt ) );

    $first_name = $appt->customer_first_name;
    $full_name  = $appt->customer_name;
    $last_name  = trim( str_replace( $first_name, '', $full_name ) );

    $emp_parts      = explode( ' ', $appt->employee_name, 2 );
    $emp_first_name = $emp_parts[0];
    $emp_last_name  = isset( $emp_parts[1] ) ? $emp_parts[1] : '';

    $template = srfa_get_option( 'message_template' );

    $message = str_replace(
        [
            '%location_name%',
            '%customer_first_name%',
            '%customer_last_name%',
            '%customer_full_name%',
            '%employee_first_name%',
            '%employee_last_name%',
            '%employee_full_name%',
            '%service_name%',
            '%appointment_date%',
            '%appointment_start_time%',
        ],
        [
            $appt->location_name,
            $first_name,
            $last_name,
            $full_name,
            $emp_first_name,
            $emp_last_name,
            $appt->employee_name,
            $appt->service_name,
            $date_str,
            $time_str,
        ],
        $template
    );

    if ( mb_strlen( $message ) > 459 ) {
        $message = mb_substr( $message, 0, 456 ) . '...';
    }

    return $message;
}


function srfa_send_reminder( $appt ) {
    $phone = srfa_format_phone( $appt->customer_phone );
    if ( ! $phone ) {
        srfa_insert_log( $appt, 'skipped', null, 'Numéro invalide : ' . $appt->customer_phone );
        return;
    }

    $message = srfa_build_message( $appt );
    $api_key = srfa_get_option( 'api_key' );
    $sender  = srfa_get_option( 'sender' );
    $sandbox = (bool) srfa_get_option( 'sandbox' );

    $payload = [
        'apiKey'       => $api_key,
        'phoneNumbers' => $phone,
        'sender'       => $sender,
        'gamme'        => 1,
        'sandbox'      => $sandbox ? 1 : 0,
        'message'      => $message,
    ];

    error_log( sprintf(
        '[SMS Reminder] Envoi SMS — RDV #%d, %s (%s), sandbox:%s',
        $appt->appointment_id,
        $appt->customer_name,
        $phone,
        $sandbox ? 'OUI' : 'NON'
    ) );

    $response = wp_remote_post( SRFA_API_URL, [
        'timeout'     => 15,
        'redirection' => 3,
        'httpversion' => '1.1',
        'headers'     => [ 'Content-Type' => 'application/json' ],
        'body'        => wp_json_encode( $payload ),
    ] );

    if ( is_wp_error( $response ) ) {
        $err = $response->get_error_message();
        error_log( "[SMS Reminder] Erreur réseau — RDV #{$appt->appointment_id} : {$err}" );
        srfa_insert_log( $appt, 'failed', null, 'Erreur réseau : ' . $err );
        return;
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body      = wp_remote_retrieve_body( $response );
    $data      = json_decode( $body, true );

    error_log( "[SMS Reminder] Réponse API (HTTP {$http_code}) — RDV #{$appt->appointment_id} : {$body}" );

    if ( $http_code === 200 && ! empty( $data['success'] ) && $data['success'] === true ) {
        $message_id = isset( $data['message_id'] ) ? (string) $data['message_id'] : null;
        srfa_insert_log( $appt, 'pending', $message_id, null );
        error_log( "[SMS Reminder] SMS accepté — RDV #{$appt->appointment_id}, message_id:{$message_id}" );
    } else {
        $error_code = isset( $data['code'] ) ? $data['code'] : $http_code;
        $error_msg  = srfa_api_error_label( $error_code ) . ' (code ' . $error_code . ')';
        srfa_insert_log( $appt, 'failed', null, $error_msg );
        error_log( "[SMS Reminder] Échec API — RDV #{$appt->appointment_id} : {$error_msg}" );
    }
}


function srfa_api_error_label( $code ) {
    $labels = [
        1  => 'Clé API manquante',
        2  => 'Champ phoneNumbers manquant',
        10 => 'Clé API incorrecte',
        11 => 'Crédits insuffisants',
        14 => 'Numéro en liste STOP SMS',
        20 => 'Compte désactivé',
        30 => 'Compte bloqué',
        42 => 'SMS low-cost limité à 160 caractères',
        50 => 'Maximum 500 numéros par requête dépassé',
        90 => 'Syntaxe JSON malformée',
        96 => 'Adresse IP non autorisée',
    ];
    return isset( $labels[ $code ] ) ? $labels[ $code ] : 'Erreur inconnue';
}


// ─── Insertion d'un log ───────────────────────────────────────────────────────

function srfa_insert_log( $appt, $status, $message_id = null, $error_msg = null ) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . SRFA_LOG_TABLE,
        [
            'appointment_id'       => (int) $appt->appointment_id,
            'customer_phone'       => $appt->customer_phone,
            'customer_name'        => $appt->customer_name,
            'appointment_datetime' => $appt->appointment_datetime,
            'service_name'         => $appt->service_name,
            'employee_name'        => $appt->employee_name,
            'location_name'        => $appt->location_name,
            'sms_status'           => $status,
            'sms_message_id'       => $message_id,
            'error_message'        => $error_msg,
            'sent_at'              => ( $status !== 'skipped' ) ? current_time( 'mysql' ) : null,
            'created_at'           => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
}


// ─── Endpoint REST : accusé de réception SMS Partner (DLR) ───────────────────

add_action( 'rest_api_init', function () {
    register_rest_route( 'srfa/v1', '/sms-delivery', [
        'methods'             => 'POST',
        'callback'            => 'srfa_delivery_webhook',
        'permission_callback' => '__return_true',
    ] );
} );

function srfa_delivery_webhook( WP_REST_Request $request ) {
    global $wpdb;

    $body = $request->get_json_params();
    error_log( '[SMS Reminder] Webhook DLR reçu : ' . wp_json_encode( $body ) );

    if ( empty( $body['msgId'] ) || empty( $body['status'] ) ) {
        error_log( '[SMS Reminder] Webhook DLR invalide — champs msgId ou status manquants.' );
        return new WP_REST_Response( [ 'error' => 'invalid_payload' ], 400 );
    }

    $msg_id     = sanitize_text_field( $body['msgId'] );
    $raw_status = sanitize_text_field( $body['status'] );

    $status_map      = [
        'delivered'     => 'delivered',
        'not delivered' => 'failed',
        'waiting'       => 'pending',
        'ko'            => 'failed',
    ];
    $internal_status = isset( $status_map[ $raw_status ] ) ? $status_map[ $raw_status ] : 'failed';

    $log_tbl = $wpdb->prefix . SRFA_LOG_TABLE;
    $row     = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$log_tbl} WHERE sms_message_id = %s LIMIT 1",
        $msg_id
    ) );

    if ( ! $row ) {
        error_log( "[SMS Reminder] Webhook DLR — aucun log pour message_id : {$msg_id}" );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    $wpdb->update(
        $log_tbl,
        [ 'sms_status' => $internal_status, 'delivery_at' => current_time( 'mysql' ) ],
        [ 'id' => $row->id ],
        [ '%s', '%s' ],
        [ '%d' ]
    );

    error_log( "[SMS Reminder] Webhook DLR — log #{$row->id} → {$internal_status}" );
    return new WP_REST_Response( [ 'ok' => true ], 200 );
}


// ─── Purge hebdomadaire ───────────────────────────────────────────────────────

function srfa_purge_old_logs() {
    global $wpdb;

    $days    = (int) srfa_get_option( 'purge_days', 30 );
    $log_tbl = $wpdb->prefix . SRFA_LOG_TABLE;
    $cutoff  = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

    $deleted = $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$log_tbl} WHERE created_at < %s",
        $cutoff
    ) );

    error_log( "[SMS Reminder] Purge : {$deleted} log(s) supprimé(s) (rétention : {$days} jours)." );
}


// ─── Menus d'administration ───────────────────────────────────────────────────

add_action( 'admin_menu', 'srfa_add_admin_menus' );

function srfa_add_admin_menus() {
    // Page Logs (Outils)
    add_management_page(
        'SMS Reminder — Logs',
        'SMS Reminder Logs',
        'manage_options',
        'srfa-logs',
        'srfa_render_logs_page'
    );
    // Page Réglages (Réglages)
    add_options_page(
        'SMS Reminder — Réglages',
        'SMS Reminder',
        'manage_options',
        'srfa-settings',
        'srfa_render_settings_page'
    );
}

/**
 * Footer de branding — affiché en bas des pages admin du plugin.
 */
function srfa_render_branding_footer() {
    ?>
    <div style="margin-top:32px;padding:14px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#64748b;text-align:center;">
        SMS Reminder for Amelia — v<?php echo esc_html( SRFA_VERSION ); ?>
        &middot; Développé par
        <a href="https://capitainesite.com/" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:none;font-weight:600;">Capitaine Site</a>,
        agence experte WordPress.
    </div>
    <?php
}

function srfa_render_logs_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    require_once SRFA_PLUGIN_DIR . 'admin-page.php';
}

function srfa_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    require_once SRFA_PLUGIN_DIR . 'settings-page.php';
}


// ─── Enregistrement des réglages via Settings API ────────────────────────────

add_action( 'admin_init', 'srfa_register_settings' );

function srfa_register_settings() {
    register_setting(
        'srfa_settings_group',
        SRFA_OPTION_KEY,
        [
            'sanitize_callback' => 'srfa_sanitize_settings',
            'default'           => [],
        ]
    );
}

function srfa_sanitize_settings( $input ) {
    $clean = [];

    // Clé API : on ne la sauvegarde pas si elle est verrouillée par define()
    if ( ! srfa_is_locked( 'api_key' ) ) {
        $clean['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
    }

    if ( ! srfa_is_locked( 'sandbox' ) ) {
        $clean['sandbox'] = ! empty( $input['sandbox'] );
    }

    if ( ! srfa_is_locked( 'sender' ) ) {
        $sender = sanitize_text_field( $input['sender'] ?? 'Reminder' );
        // SMS Partner : 3-11 caractères, alphanumérique, sans accents
        $sender = preg_replace( '/[^a-zA-Z0-9]/', '', $sender );
        $sender = substr( $sender, 0, 11 );
        if ( strlen( $sender ) < 3 ) { $sender = 'Reminder'; }
        $clean['sender'] = $sender;
    }

    $clean['message_template']   = sanitize_textarea_field( $input['message_template'] ?? '' );
    $clean['purge_days']         = max( 7, min( 365, (int) ( $input['purge_days'] ?? 30 ) ) );
    $clean['reminder_hours_min'] = max( 1,  min( 47, (int) ( $input['reminder_hours_min'] ?? 23 ) ) );
    $clean['reminder_hours_max'] = max( 2,  min( 48, (int) ( $input['reminder_hours_max'] ?? 25 ) ) );

    if ( $clean['reminder_hours_min'] >= $clean['reminder_hours_max'] ) {
        $clean['reminder_hours_max'] = $clean['reminder_hours_min'] + 2;
    }

    // Fréquence du cron — valider que c'est une valeur autorisée
    $allowed_freqs = array_keys( srfa_cron_frequencies() );
    $new_freq      = (int) ( $input['cron_frequency'] ?? 15 );
    if ( ! in_array( $new_freq, $allowed_freqs, true ) ) { $new_freq = 15; }
    $clean['cron_frequency'] = $new_freq;

    // Si la fréquence a changé, replanifier le cron
    $old_options = get_option( SRFA_OPTION_KEY, [] );
    $old_freq    = (int) ( $old_options['cron_frequency'] ?? 15 );
    if ( $new_freq !== $old_freq ) {
        srfa_clear_crons();
        wp_schedule_event( time(), 'srfa_every_' . $new_freq . 'min', 'srfa_hourly_send' );
        // Replanifier la purge aussi (elle n'est pas touchée par la fréquence, mais clear_crons la supprime)
        $next_monday = strtotime( 'next monday 03:00' );
        wp_schedule_event( $next_monday, 'weekly', 'srfa_weekly_purge' );
        error_log( "[SMS Reminder] Fréquence du cron changée : {$old_freq}min → {$new_freq}min" );
    }

    return $clean;
}


// ─── Action manuelle depuis l'admin ──────────────────────────────────────────

add_action( 'admin_post_srfa_run_now', 'srfa_admin_run_now' );

function srfa_admin_run_now() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'srfa_run_now' );

    srfa_process_reminders();

    wp_safe_redirect( add_query_arg(
        [ 'page' => 'srfa-logs', 'srfa_ran' => '1' ],
        admin_url( 'tools.php' )
    ) );
    exit;
}


// ─── Lien "Réglages" depuis la liste des plugins ──────────────────────────────

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=srfa-settings' ) ) . '">Réglages</a>';
    $logs_link     = '<a href="' . esc_url( admin_url( 'tools.php?page=srfa-logs' ) ) . '">Logs</a>';
    array_unshift( $links, $settings_link, $logs_link );
    return $links;
} );


// ─── Vérification table au chargement ────────────────────────────────────────

add_action( 'plugins_loaded', function () {
    global $wpdb;
    $table = $wpdb->prefix . SRFA_LOG_TABLE;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        srfa_create_table();
        srfa_register_crons();
    }
}, 5 );
