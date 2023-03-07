(function($){

    $(document).on('change', "#woocommerce_slickpay_account_type", function(){
        var accountType = $(this).val();

        if (accountType == 'merchant') {
            $("#woocommerce_slickpay_api_module").val('invoices').trigger('change').find('option[value="transfers"]').attr('disabled', 'disabled');
            $("#woocommerce_slickpay_user_account").val('').trigger('change').attr('disabled', 'disabled');
        } else {
            $('#woocommerce_slickpay_api_module option[value="transfers"]').removeAttr('disabled');
            $("#woocommerce_slickpay_user_account").removeAttr('disabled');
        }
    });

    setTimeout(() => {
        $("#woocommerce_slickpay_account_type").trigger('change');
    }, 350);

})(jQuery);