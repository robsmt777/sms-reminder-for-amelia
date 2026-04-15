<?php
/**
 * Gateway interface — all SMS gateway implementations must implement this contract.
 *
 * A gateway encapsulates everything specific to one SMS provider:
 *   - identity (id, label, description)
 *   - configuration schema (fields the user must fill in the settings page)
 *   - send() logic (API call)
 *   - handle_dlr() logic (webhook payload parsing)
 *
 * The plugin core (cron, settings page, logs) stays gateway-agnostic and
 * dispatches work to the active gateway at runtime.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface SRFA_Gateway_Interface {

    /**
     * Unique identifier used in the option key, REST route and logs.
     * Lowercase, no spaces, e.g. "smspartner", "ovh", "twilio".
     */
    public function get_id();

    /**
     * Human-readable label shown in the gateway selector.
     */
    public function get_label();

    /**
     * Short description shown below the gateway label in the selector.
     */
    public function get_description();

    /**
     * Field schema for this gateway's settings section.
     *
     * Each entry: [
     *   'key'         => 'api_key',
     *   'type'        => 'text' | 'password' | 'checkbox' | 'select',
     *   'label'       => __( 'Label', 'sms-reminder-for-amelia' ),
     *   'description' => __( 'Help text', 'sms-reminder-for-amelia' ),
     *   'default'     => '',
     *   'placeholder' => '',
     *   'options'     => [ 'key' => 'label' ],       // for select
     *   'sanitize'    => 'sanitize_text_field',      // optional callback
     * ]
     *
     * @return array
     */
    public function get_fields();

    /**
     * Send one SMS.
     *
     * @param string $phone    E.164 number (+33...)
     * @param string $message  Message body (already rendered from template).
     * @param array  $settings Per-gateway settings (keys from get_fields()).
     * @return array {
     *     @type bool    $success
     *     @type string  $message_id  Provider-side ID (used later by DLR webhook).
     *     @type string  $error       Human-readable error if $success = false.
     * }
     */
    public function send( $phone, $message, $settings );

    /**
     * Parse a DLR webhook payload and return normalized data.
     *
     * @param WP_REST_Request $request
     * @return array|null {
     *     @type string $message_id
     *     @type string $status  'delivered' | 'failed' | 'pending'
     * } Null if the payload is invalid/ignored.
     */
    public function handle_dlr( WP_REST_Request $request );
}
