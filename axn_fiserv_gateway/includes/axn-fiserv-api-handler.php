<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Refunds and other API requests such as void.
 * @since 1.0.0
 */
class Axn_fiserv_API_Handler
{

    /** @var string API Username */
    public static $api_username;

    /** @var string API Password */
    public static $api_password;

    /** @var string API URL */
    public static $api_url;

    /** @var string API Key Password */
    public static $certificate_key_password;

    /** @var string API Server Trust PEM */
    public static $server_trust_pem;

    /** @var string API Client Certificate PEM */
    public static $client_certificate_pemfile;

    /** @var string API Client Certificate Key */
    public static $client_certificate_keyfile;

    public static function get_refund_request($request_param)
    {

        $request = array(
            'URL' => self::$api_url,
            'USER' => self::$api_username,
            'PWD' => self::$api_password,
            'KEY_PWD' => self::$certificate_key_password,
            'TRUST_PEM' => self::$server_trust_pem,
            'CERT_PEM' => self::$client_certificate_pemfile,
            'CERT_KEY' => self::$client_certificate_keyfile,
            'ORDERID' => $request_param['order_hash_id'],
            'TRANSACTIONID' => $request_param['transaction_id'],
            'METHOD' => $request_param['method'],
        );
        if (!is_null($request_param['amount'])) {
            $request['AMT'] = number_format($request_param['amount'], 2, '.', '');
            $request['CURRENCYCODE'] = $request_param['order_currency'];
        }
        if ($request_param['method'] == "return") {
            $request['XML'] = '<?xml version="1.0" encoding="utf-8"?>' .
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1" xmlns:ipg="http://ipg-online.com/ipgapi/schemas/ipgapi">' .
                '<soap:Body>' .
                '<ipgapi:IPGApiOrderRequest
                    xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1"
                    xmlns:ipgapi="http://ipg-online.com/ipgapi/schemas/ipgapi">
                    <v1:Transaction>
                    <v1:CreditCardTxType>
                    <v1:Type>return</v1:Type>
                    </v1:CreditCardTxType>
                    <v1:Payment>
                    <v1:ChargeTotal>' . $request['AMT'] . '</v1:ChargeTotal>
                    <v1:Currency>' . $request['CURRENCYCODE'] . '</v1:Currency>
                    </v1:Payment>
                    <v1:TransactionDetails>
                    <v1:OrderId>' . $request['ORDERID'] . '</v1:OrderId>
                    </v1:TransactionDetails>
                    </v1:Transaction>
                    </ipgapi:IPGApiOrderRequest>' .
                '</soap:Body>' .
                '</soap:Envelope>';
        }
        else if ($request_param['method'] == "void") {
            $request['XML'] = '<?xml version="1.0" encoding="utf-8"?>' .
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1" xmlns:ipg="http://ipg-online.com/ipgapi/schemas/ipgapi">' .
                '<soap:Body>' .
                '<ipgapi:IPGApiOrderRequest
                    xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1"
                    xmlns:ipgapi="http://ipg-online.com/ipgapi/schemas/ipgapi">
                    <v1:Transaction>
                    <v1:CreditCardTxType>
                    <v1:Type>void</v1:Type>
                    </v1:CreditCardTxType>
                    <v1:TransactionDetails>
                    <v1:IpgTransactionId>' . $request['TRANSACTIONID'] . '</v1:IpgTransactionId>
                    </v1:TransactionDetails>
                    </v1:Transaction>
                    </ipgapi:IPGApiOrderRequest>' .
                '</soap:Body>' .
                '</soap:Envelope>';
        }
        return $request;
    }

    /**
     * Refund an order via Axn_fiserv.
     * @param  WC_Order $order
     * @param  float    $amount
     * @param  string   $reason
     * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
     */
    public static function refund_transaction($request_param)
    {

        $request = self::get_refund_request($request_param);
        require_once(dirname(__FILE__) . '/axn-fiserv-request.php');
        $process_request = new Axn_fiserv_Request();
        $api_response = $process_request->build_request($request);
        return $api_response;
    }

}