define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'domReady!'
], function($,customerData) {
    return function(config, element)
    {
        $('#CxsEe').focus();

        $("#update-button").on('click', function (event) {
            if(jQuery("#digital-form").validation('isValid')){
                $("#update-button").prop("disabled", true);
                $.ajax({
                    showLoader: true,
                    url: BASE_URL + 'paymentlink/subscription/update',
                    data: $("#digital-form").serialize(),
                    method: "POST",
                    success: function (response) {
                        customerData.reload(['customer']);
                        location.reload();
                    }
                });
            }
        });
    };
});
