define([
    'jquery',
    'mage/translate',
    'openpay-data.v1.min.colombia',
    'domReady!'
], function($,$t) {
    return function(config, element)
    {
        var FormId = element.id;

        OpenPay.setId(config.merchantId);
        OpenPay.setApiKey(config.apiKey);
        OpenPay.setSandboxMode(config.sandBoxMode);
        console.log('The JS openpayForm is load');
        //Se genera el id de dispositivo
        var deviceSessionId = OpenPay.deviceData.setup(FormId, "deviceIdHiddenFieldName");

        $('#pay-button').on('click', function (event) {
            $('body').trigger('processStart');
            event.preventDefault();
            console.log('click me!');
            let cardType = $('input:radio[name="is_digital"]:checked').val();
            let holderName = $('input[data-openpay-card=holder_name]').val();
            $('#validateIsDigital').hide('fast');
            $('#titular-error').hide('fast');

            if ( cardType >= 0 ){
                if(holderName.length > 0){
                    $("#pay-button").prop("disabled", true);
                    OpenPay.token.extractFormAndCreate(FormId, sucess_callbak, error_callbak);
                }else{
                    $('body').trigger('processStop');
                    $('input[data-openpay-card=holder_name]').focus();
                    $('#titular-error').show('fast');
                }
            }else {
                $('body').trigger('processStop');
                $('#validateIsDigital').show('fast');
            }

        });

        var sucess_callbak = function (response) {
            var token_id = response.data.id;
            $('#token_id').val(token_id);
            $('#payment-form').submit();
        };

        var error_callbak = function (response) {
            var desc = response.data.description != undefined ? response.data.description : response.message;
            $('.page.messages').append('<div id="wolf-message" class="message info">'+$t(desc)+'</div>');
            window.scrollTo(0, 0);
            setTimeout(function() {
                $('.page.messages').find("#wolf-message").remove();
            }, 5000);

            $("#pay-button").prop("disabled", false);
            $('body').trigger('processStop');
        };
    };
});
