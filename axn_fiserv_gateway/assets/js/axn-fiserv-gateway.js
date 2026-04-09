(function($) {
    $.fn.localPaymentMode = function() {
        
        var localpayment = $( "#fd_local_payment" ).val();
        //console.log(localpayment);
        if(fd_setting.localPaySetting.hasOwnProperty(localpayment)){
            localpaymentSelected = fd_setting.localPaySetting[localpayment];
            if(localpaymentSelected['card_support'] == false){
                $( '.wc-payment-form').hide();
                $( '.local-payment-form').show();
            } else {
                $( '.wc-payment-form').show();
                $( '.local-payment-form').show();
            }
        //console.log(localpaymentSelected);
        //console.log(localpaymentSelected['card_support']);
        }
    };
})(jQuery);
