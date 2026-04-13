<?php
/**
 * Routine de désinstallation du plugin SMS Reminder for Amelia.
 *
 * Exécuté par WordPress lors d'une désinstallation depuis l'interface admin.
 * Supprime :
 *   - La table wp_srfa_logs
 *   - Les événements cron orphelins (sécurité si la déactivation a été sautée)
 *
 * La table n'est PAS supprimée lors de la simple désactivation — uniquement ici.
 */

// Protection : ce fichier ne doit être appelé que par WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// ── Suppression de la table de logs ──────────────────────────────────────────
$table = $wpdb->prefix . 'srfa_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// ── Nettoyage des crons (au cas où déactivation aurait été sautée) ────────────
$hooks = [ 'srfa_hourly_send', 'srfa_weekly_purge' ];
foreach ( $hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}

error_log( '[SMS Reminder] Plugin désinstallé — table et crons supprimés.' );
