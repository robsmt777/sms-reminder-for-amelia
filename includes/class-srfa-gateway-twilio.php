<?php
/**
 * Twilio gateway (https://www.twilio.com/).
 *
 * Uses the REST v2010-04-01 Messages endpoint with HTTP Basic Auth
 * (Account SID + Auth Token).
 *
 * DLR: Twilio posts form-encoded StatusCallback notifications to the
 * registered URL. We parse MessageSid + MessageStatus.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SRFA_Gateway_Twilio implements SRFA_Gateway_Interface {

    const API_BASE = 'https://api.twilio.com/2010-04-01/Accounts/';

    public function get_id() { return 'twilio'; }

    public function get_label() {
        return __( 'Twilio', 'sms-reminder-for-amelia' );
    }

    public function get_description() {
        return __( 'Global SMS provider — excellent worldwide coverage, deliverability, and developer tooling.', 'sms-reminder-for-amelia' );
    }

    public function get_fields() {
        return [
            [
                'key'         => 'account_sid',
                'type'        => 'text',
                'label'       => __( 'Account SID', 'sms-reminder-for-amelia' ),
                'description' => __( 'Twilio Console → Account Info → Account SID (starts with "AC…").', 'sms-reminder-for-amelia' ),
                'default'     => '',
                'placeholder' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'sanitize'    => 'sanitize_text_field',
            ],
            [
                'key'         => 'auth_token',
                'type'        => 'password',
                'label'       => __( 'Auth Token', 'sms-reminder-for-amelia' ),
                'description' => __( 'Twilio Console → Account Info → Auth Token.', 'sms-reminder-for-amelia' ),
                'default'     => '',
                'placeholder' => __( 'Your Twilio Auth Token', 'sms-reminder-for-amelia' ),
                'sanitize'    => 'sanitize_text_field',
            ],
            [
                'key'         => 'from_number',
                'type'        => 'text',
                'label'       => __( 'From number', 'sms-reminder-for-amelia' ),
                'description' => __( 'E.164 format (e.g. +33712345678). Must be a Twilio-owned or verified number, or a Messaging Service SID (MG…).', 'sms-reminder-for-amelia' ),
                'default'     => '',
                'placeholder' => '+33712345678',
                'sanitize'    => 'sanitize_text_field',
            ],
            [
                'key'         => 'use_messaging_service',
                'type'        => 'checkbox',
                'label'       => __( 'Use Messaging Service SID', 'sms-reminder-for-amelia' ),
                'description' => __( 'Check if the "From number" field above is actually a Messaging Service SID (MGxxxxxxxx…).', 'sms-reminder-for-amelia' ),
                'default'     => false,
            ],
        ];
    }

    public function send( $phone, $message, $settings ) {
        $sid   = trim( $settings['account_sid'] ?? '' );
        $token = trim( $settings['auth_token']  ?? '' );
        $from  = trim( $settings['from_number'] ?? '' );

        if ( $sid === '' || $token === '' || $from === '' ) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => __( 'Twilio: Account SID, Auth Token or From number missing.', 'sms-reminder-for-amelia' ),
            ];
        }

        $body = [
            'To'   => $phone,
            'Body' => $message,
        ];

        if ( ! empty( $settings['use_messaging_service'] ) ) {
            $body['MessagingServiceSid'] = $from;
        } else {
            $body['From'] = $from;
        }

        $url = self::API_BASE . rawurlencode( $sid ) . '/Messages.json';

        $response = wp_remote_post( $url, [
            'timeout'     => 20,
            'httpversion' => '1.1',
            'headers'     => [
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'        => http_build_query( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => __( 'Network error:', 'sms-reminder-for-amelia' ) . ' ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $data      = json_decode( wp_remote_retrieve_body( $response ), true );

        // Twilio returns 201 Created on successful enqueue.
        if ( ( $http_code === 200 || $http_code === 201 ) && ! empty( $data['sid'] ) ) {
            return [
                'success'    => true,
                'message_id' => (string) $data['sid'],
                'error'      => null,
            ];
        }

        $msg = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'sms-reminder-for-amelia' );
        $code = isset( $data['code'] ) ? $data['code'] : $http_code;
        return [
            'success'    => false,
            'message_id' => null,
            'error'      => sprintf( 'Twilio: %s (code %s)', $msg, $code ),
        ];
    }

    public function handle_dlr( WP_REST_Request $request ) {
        // Twilio sends form-encoded POST
        $message_sid    = $request->get_param( 'MessageSid' );
        $message_status = $request->get_param( 'MessageStatus' );

        if ( empty( $message_sid ) || empty( $message_status ) ) {
            return null;
        }

        // Twilio statuses: queued, sending, sent, delivered, undelivered, failed, read
        $status_map = [
            'queued'      => 'pending',
            'sending'     => 'pending',
            'sent'        => 'pending',   // network accepted, not yet delivered
            'delivered'   => 'delivered',
            'read'        => 'delivered',
            'undelivered' => 'failed',
            'failed'      => 'failed',
        ];
        $raw             = sanitize_text_field( $message_status );
        $internal_status = isset( $status_map[ $raw ] ) ? $status_map[ $raw ] : 'failed';

        return [
            'message_id' => sanitize_text_field( $message_sid ),
            'status'     => $internal_status,
        ];
    }
}
