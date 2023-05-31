define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'domReady!'
], function($,customerData) {
    return function(config, element)
    {
        /*actualiza customer section para borrar las notificaciones en caso de que ya se haya actualizado*/
        customerData.reload(['customer']).done(function(){

            if(customerData.get('customer')().notification_number) {
                $('body').addClass('show-notification');
            }

            if(customerData.get('customer')().notification_update_card) {
                $(".form-change-container").show();
            }

            $('#updateMethod').on('click', function (event) {
                $(".form-change-container").show();
                $("input[name=is_digital]").focus();
            });
        });
    };
});
