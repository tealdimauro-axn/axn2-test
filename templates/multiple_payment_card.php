<?php if (!empty($installment_payment)) { ?>
    <div id="credit-card" class="firstdata-credit-card">
        

        <p>
            <input id="_installmentpaymet" type="radio" name="_fullpaymet" value="multiplepayment"  checked="checked" /> 
            <span><?php _e('Pagar en Cuotas', 'woocommerce-firstdata') ?></span>
        <div id="installment_div" style="padding-left:40px;font-size: small;display:block;">
            <?php _e('Seleccionar Cuotas', 'woocommerce-firstdata'); ?>
            <div id="installment_itemdiv" style="padding-left:40px;padding-top:10px;line-height: 100%;">
              
              
                <?php
                foreach ($installment_payment as $key => $Installment):
                    if (!empty($Installment['multipayment_description'])):
                        global $woocommerce;
                        $totalcartamount = $woocommerce->cart->total;
                        $cantCuotas = $Installment['multipayment_quotas'];
                        $interes = $Installment['multipayment_interest'];
                        $totalAmt = ($totalcartamount * $interes);
                        $cuotasAmount = $totalAmt / $cantCuotas;
 
 if($key == 0){  ?>
     <p>
                            <input id="_multiplepayment" type="radio" name="_multiplepayment" value="<?php echo $Installment['multipayment_id']; ?>"  /> 
                            <span><?php _e($Installment['multipayment_description'] . ' de <b>' . wc_price($cuotasAmount) . '</b> - Total: <b>' . wc_price($totalAmt) . '</b>') ?></span>
                        </p>
  <?php } else {  ?>
 
     <p>
                            <input id="_multiplepayment" type="radio" name="_multiplepayment" value="<?php echo $Installment['multipayment_id']; ?>"/> 
                            <span><?php _e($Installment['multipayment_description'] . ' de <b>' . wc_price($cuotasAmount) . '</b> - Total: <b>' . wc_price($totalAmt) . '</b>') ?></span>
                        </p>
  <?php }  ?>
 
                       
                    
                        <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
    </p>
<?php } ?>
</div>

<script language='javascript'>
  
    jQuery(document.body).bind('updated_checkout', function () {
      
       var value = 1;

    jQuery("input[name=_multiplepayment][value=" + value + "]").prop('checked', true);
      
    
});

</script>