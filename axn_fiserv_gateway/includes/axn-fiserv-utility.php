<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates requests to send to Axn_fiserv.
 */
class Axn_fiserv_Utility
{

    public static function getFeatures()
    {
        return array(
            "arg" => array("posnet" => array(
                    "plugin_name" => "Tarjetas de Crédito",
                    "reseller_name" => "Tarjetas de Crédito",
                    "logo" => WC_HTTPS::force_https_url(WC_AXN_FISERV_PLUGIN_URL) . "/assets/images/e-posnet.png",
                    "description" => "Pay securely with",
                    "customer_detail_title" => " ",
                    "customer_detail" => " ",
                    "contact_support_title" => "Para dudas, consultas o revisión de estados de trámites, puede contactarse con nosotros de las siguientes formas:",
                    "contact_support" => "Teléfono desde Capital Federal y GBA:</br>(011) 4126-3000 – de Lunes a Viernes de 9 a 21hs Teléfono desde el Interior del país:</br>0810-999-7676 – de Lunes a Viernes de 9 a 21hs Completando el formulario online: <a href='http://www.posnet.com.ar/atencion'>http://www.posnet.com.ar/atencion</a>",
                    "produrl" => "https://www5.ipg-online.com/connect/gateway/processing",
                    "testurl" => "https://test.ipg-online.com/connect/gateway/processing",
                    "apiurl" => "https://test.ipg-online.com/ipgapi/services",
                    "prodapiurl" => "https://www5.ipg-online.com/ipgapi/services",
                    "local_payment" => array(),
                    "dynamic_merchant_name" => 'yes',
                    "instalments" => 'yes',
                    "secure_pay" => 'yes',
                    "dcc_skip_offer" => 'no',
                    "refunds" => 'yes',
                    "card_type" => 'no',
                )),
        );
    }
}
?>