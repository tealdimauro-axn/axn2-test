<?php
$firstData = new wanderlust_Firstdata_Gateway();
$html = "<option value=''>--".__('Please Select', 'woocommerce-firstdata')."--</option>";
foreach ($local_payment as $key => $local_options) {
    if(false === WC()->cart->needs_shipping_address() && $firstData->getCardSupportLocalPay($key, 'S')== false){
        continue;
    }
    $html .= '<option value="' . $key . '">' . ucwords($local_options['name']) . '</option>';
}
?>
<div id="credit-card" class="wc-payment-form local-payment-form">

    <p style='padding-top: 30px;'>
        <input id="_localpayment" type="checkbox" name="_localpayment" value="localpayment"/> 
        <span><?php _e('Use Local/Alternate Payment Method', 'woocommerce-firstdata') ?></span>
    </p>
    <div id="_localpayment_details" style="display:none;">
        <fieldset id="wc-wanderlust_firstdata_gateway-cc-form">
            <p class="form-row form-row-wide woocommerce-validated">
                <label for="wanderlust_firstdata_gateway-card-number" style="width:40%;float:left;
                       padding-top: 10px;"><?php _e('Choose Your Local Methods :', 'woocommerce-firstdata') ?></label>
                <span class="form-row form-row-wide" id="local_payment_field">
                    <select name="local_payment_option" id="fd_local_payment" class="fd_local_payment" onchange="jQuery(this).localPaymentMode();">
                            <?php echo $html; ?>
                    </select>
                </span>		
            </p><div class="clear"></div>
        </fieldset>
    </div>
</div>

<script language='javascript'>
    jQuery('#_localpayment').on( 'click', function(){
        this.checked?jQuery('#_localpayment_details').show(1000):jQuery('#_localpayment_details').hide(1000); //time for show
    });
</script>