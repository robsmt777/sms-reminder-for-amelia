<?php
/**
 * SMS Partner gateway (https://www.smspartner.fr/).
 *
 * Legacy gateway — v1.0–1.2 of the plugin used this exclusively.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SRFA_Gateway_SMSPartner implements SRFA_Gateway_Interface {

    const API_URL = 'https://api.smspartner.fr/v1/send';

    public function get_id() { return 'smspartner'; }

    public function get_label() {
        return __( 'SMS Partner', 'sms-reminder-for-amelia' );
    }

    public function get_description() {
        return __( 'French SMS provider — reliable, competitive pricing, native French support.', 'sms-reminder-for-amelia' );
    }

    public function get_fields() {
        return [
            [
                'key'         => 'api_key',
                'type'        => 'password',
                'label'       => __( 'API key', 'sms-reminder-for-amelia' ),
                'description' => __( 'Find your key in SMS Partner dashboard → Settings → API.', 'sms-reminder-for-amelia' ),
                'default'     => '',
                'placeholder' => __( 'Your SMS Partner API key', 'sms-reminder-for-amelia' ),
                'sanitize'    => 'sanitize_text_field',
            ],
            [
                'key'         => 'sender',
                'type'        => 'text',
                'label'       => __( 'Sender', 'sms-reminder-for-amelia' ),
                'description' => __( '3 to 11 alphanumeric characters, no spaces or accents.', 'sms-reminder-for-amelia' ),
                'default'     => 'Reminder',
                'placeholder' => 'Reminder',
                'maxlength'   => 11,
                'sanitize'    => [ __CLASS__, 'sanitize_sender' ],
            ],
            [
                'key'         => 'sandbox',
                'type'        => 'checkbox',
                'label'       => __( 'Sandbox mode', 'sms-reminder-for-amelia' ),
                'description' => __( 'Enable test mode (no SMS actually sent, no credit used).', 'sms-reminder-for-amelia' ),
                'default'     => true,
            ],
        ];
    }

    public static function sanitize_sender( $value ) {
        $value = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $value );
        $value = substr( $value, 0, 11 );
        if ( strlen( $value ) < 3 ) { $value = 'Reminder'; }
        return $value;
    }

    public function send( $phone, $message, $settings ) {
        $payload = [
            'apiKey'       => $settings['api_key'] ?? '',
            'phoneNumbers' => $phone,
            'sender'       => $settings['sender'] ?? 'Reminder',
            'gamme'        => 1,
            'sandbox'      => ! empty( $settings['sandbox'] ) ? 1 : 0,
            'message'      => $message,
        ];

        $response = wp_remote_post( self::API_URL, [
            'timeout'     => 15,
            'redirection' => 3,
            'httpversion' => '1.1',
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => __( 'Network error:', 'sms-reminder-for-amelia' ) . ' ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        if ( $http_code === 200 && ! empty( $data['success'] ) && $data['success'] === true ) {
            return [
                'success'    => true,
                'message_id' => isset( $data['message_id'] ) ? (string) $data['message_id'] : null,
                'error'      => null,
            ];
        }

        $error_code = isset( $data['code'] ) ? $data['code'] : $http_code;
        $error_msg  = self::api_error_label( $error_code ) . ' (code ' . $error_code . ')';
        return [
            'success'    => false,
            'message_id' => null,
            'error'      => $error_msg,
        ];
    }

    public function handle_dlr( WP_REST_Request $request ) {
        $body = $request->get_json_params();
        if ( empty( $body['msgId'] ) || empty( $body['status'] ) ) {
            return null;
        }

        $status_map = [
            'delivered'     => 'delivered',
            'not delivered' => 'failed',
            'waiting'       => 'pending',
            'ko'            => 'failed',
        ];
        $raw_status      = sanitize_text_field( $body['status'] );
        $internal_status = isset( $status_map[ $raw_status ] ) ? $status_map[ $raw_status ] : 'failed';

        return [
            'message_id' => sanitize_text_field( $body['msgId'] ),
            'status'     => $internal_status,
        ];
    }

    protected static function api_error_label( $code ) {
        $labels = [
            1  => __( 'Missing API key',                        'sms-reminder-for-amelia' ),
            2  => __( 'Missing phoneNumbers field',             'sms-reminder-for-amelia' ),
            10 => __( 'Invalid API key',                        'sms-reminder-for-amelia' ),
            11 => __( 'Insufficient credits',                   'sms-reminder-for-amelia' ),
            14 => __( 'Number on STOP SMS list',                'sms-reminder-for-amelia' ),
            20 => __( 'Account disabled',                       'sms-reminder-for-amelia' ),
            30 => __( 'Account blocked',                        'sms-reminder-for-amelia' ),
            42 => __( 'Low-cost SMS limited to 160 characters', 'sms-reminder-for-amelia' ),
            50 => __( 'Max 500 numbers per request exceeded',   'sms-reminder-for-amelia' ),
            90 => __( 'Malformed JSON syntax',                  'sms-reminder-for-amelia' ),
            96 => __( 'Unauthorized IP address',                'sms-reminder-for-amelia' ),
        ];
        return isset( $labels[ $code ] ) ? $labels[ $code ] : __( 'Unknown error', 'sms-reminder-for-amelia' );
    }
}
