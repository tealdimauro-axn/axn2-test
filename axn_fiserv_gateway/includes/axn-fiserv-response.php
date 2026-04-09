<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Responses.
 */
class Axn_fiserv_Response {

    /** Exception Strings
      [faultcode] => SOAP-ENV:Client
      [faultstring] => ProcessingException
      [faultcode] => SOAP-ENV:Client
      [faultstring] => MerchantException
      [detail] => Missing referenced transaction
     * * */
    public function loadXML($xml = null) {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);
        $xml_string = $doc->saveXML();
        $plainXML = $this->parseXML($xml_string);
        $result = json_decode(json_encode(SimpleXML_Load_String($plainXML, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $response = $this->array_flatten($result);
        return $response;
    }

    public function parseXML($xml = null) {
        $obj = SimpleXML_Load_String($xml);
        if ($obj === FALSE)
            return $xml;

        // GET NAMESPACES, IF ANY
        $nss = $obj->getNamespaces(TRUE);
        if (empty($nss))
            return $xml;

        // CHANGE ns: INTO ns_
        $nsm = array_keys($nss);
        foreach ($nsm as $key) {
            // A REGULAR EXPRESSION TO PARSE THE XML
            $rgx
                    = '#'               // REGEX DELIMITER
                    . '('               // GROUP PATTERN 1
                    . '\<'              // LOCATE A LEFT WICKET
                    . '/?'              // MAYBE FOLLOWED BY A SLASH
                    . preg_quote($key)  // THE NAMESPACE
                    . ')'               // END GROUP PATTERN
                    . '('               // GROUP PATTERN 2
                    . ':{1}'            // A COLON (EXACTLY ONE)
                    . ')'               // END GROUP PATTERN
                    . '#'               // REGEX DELIMITER
            ;
            // INSERT THE UNDERSCORE INTO THE TAG NAME
            $rep
                    = '$1'          // BACKREFERENCE TO GROUP 1
                    . '_'           // LITERAL UNDERSCORE IN PLACE OF GROUP 2
            ;
            // PERFORM THE REPLACEMENT
            $xml = preg_replace($rgx, $rep, $xml);
        }
        return $xml;
    }

    public function array_flatten($array) {
        if (!is_array($array)) {
            return FALSE;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function api_response($request, $response) {
        $response = $this->loadXML($response);
        $response['method'] = $request['METHOD'];
        return (object) $response;
    }

}
