<?php
/**
 * Gateway registry — central lookup for all available SMS gateways.
 *
 * Third parties can add custom gateways via the `srfa_gateways` filter:
 *
 *   add_filter( 'srfa_gateways', function ( $gateways ) {
 *       $gateways['mygw'] = new My_Custom_Gateway();
 *       return $gateways;
 *   } );
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SRFA_Gateway_Registry {

    /** @var array<string,SRFA_Gateway_Interface>|null */
    protected static $cache = null;

    /**
     * @return array<string,SRFA_Gateway_Interface>
     */
    public static function all() {
        if ( self::$cache !== null ) {
            return self::$cache;
        }
        $gateways = [
            'smspartner' => new SRFA_Gateway_SMSPartner(),
            'ovh'        => new SRFA_Gateway_OVH(),
            'twilio'     => new SRFA_Gateway_Twilio(),
        ];
        /**
         * Filter the list of registered gateways.
         *
         * @param array<string,SRFA_Gateway_Interface> $gateways
         */
        self::$cache = apply_filters( 'srfa_gateways', $gateways );
        return self::$cache;
    }

    /**
     * @param string $id
     * @return SRFA_Gateway_Interface|null
     */
    public static function get( $id ) {
        $all = self::all();
        return isset( $all[ $id ] ) ? $all[ $id ] : null;
    }

    /**
     * Returns the active gateway according to saved settings, or SMSPartner by default.
     * @return SRFA_Gateway_Interface
     */
    public static function active() {
        $options = get_option( SRFA_OPTION_KEY, [] );
        $id      = isset( $options['active_gateway'] ) ? (string) $options['active_gateway'] : 'smspartner';
        $gw      = self::get( $id );
        return $gw ?: new SRFA_Gateway_SMSPartner();
    }

    /**
     * Returns the settings array for a given gateway id, with defaults applied.
     * @return array
     */
    public static function get_settings( $id ) {
        $options = get_option( SRFA_OPTION_KEY, [] );
        $stored  = isset( $options[ 'gateway_' . $id ] ) && is_array( $options[ 'gateway_' . $id ] ) ? $options[ 'gateway_' . $id ] : [];

        $gw = self::get( $id );
        if ( ! $gw ) { return $stored; }

        $out = [];
        foreach ( $gw->get_fields() as $field ) {
            $key = $field['key'];
            if ( array_key_exists( $key, $stored ) && $stored[ $key ] !== '' ) {
                $out[ $key ] = $stored[ $key ];
            } else {
                $out[ $key ] = $field['default'] ?? '';
            }
        }
        return $out;
    }
}
