define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Ui/js/modal/confirm',
        'Magento_Customer/js/model/customer',
        'WolfSellers_PaymentLink/js/action/set-payment-method-action',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/storage',
        'Magento_Checkout/js/action/redirect-on-success',
        'domReady!'
    ],
    function ($,Component,confirmation,customer,setPaymentMethodAction,additionalValidators,fullScreenLoader,storage,redirectOnSuccessAction) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'WolfSellers_PaymentLink/payment/payment_link'
            },
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    confirmation({
                        responsive: true,
                        content: '<div class="modal-email-confirmation">' +
                            '<div class="modal-email-confirmation-content">' +
                            '<label for="confirmation_email">'+$.mage.__('Confirmación de Correo Electrónico')+'</label>' +
                            '<hr class="divider">'+
                            '<div class="description"> ' +
                            '<p>Verifica que el correo registrado sea correcto. Importante, ten en cuenta que a esta dirección te será enviado el email con las instrucciones y el link de pago para finalizar tu suscripción, y comenzar a disfrutar de los beneficios que tenemos para ti.</p>' +
                            '</div>'+
                            '<input id="confirmation_email" type="text" value="'+customerData.email+'">' +
                            '<input id="email_in_quote" type="hidden" value="'+customerData.email+'">' +
                            '</div>' +
                            '</div>',
                        clickableOverlay:false,
                        buttons: [{
                            text: $.mage.__('Regresar'),
                            class: 'modal-email-confirmation-back',
                            click: function () {
                                this.closeModal();
                            },
                        }, {
                            text: $.mage.__('Confirmar'),
                            class: 'modal-email-confirmation-confirm',

                            click: function () {
                                fullScreenLoader.startLoader();
                                $.ajax({
                                    showLoader: true,
                                    url: BASE_URL+"paymentlink/customer/confirmationemail",
                                    data: {
                                        'confirmation_email':$('#confirmation_email').val(),
                                        "email_in_quote":$('#email_in_quote').val(),
                                        'deviceIdHiddenFieldName':$("#deviceIdHiddenFieldName").val()
                                    },
                                    type: "POST",
                                    success: function (data) {
                                        if(data.success === true){
                                            if (self.validate() && additionalValidators.validate()) {
                                                self.isPlaceOrderActionAllowed(false);

                                                self.getPlaceOrderDeferredObject()
                                                    .fail(
                                                        function () {
                                                            self.isPlaceOrderActionAllowed(true);
                                                        }
                                                    ).done(
                                                    function () {
                                                        self.afterPlaceOrder();

                                                        if (self.redirectAfterPlaceOrder) {
                                                            redirectOnSuccessAction.execute();
                                                        }
                                                    }
                                                );

                                                return true;
                                            }
                                        }
                                        alert(data.message);
                                        fullScreenLoader.stopLoader();
                                    },
                                    error: function (error) {
                                        alert('no fue posible confirmar el correo electronico');
                                        fullScreenLoader.stopLoader();
                                    }
                                });
                            },
                        }]
                    });
                }
            }
        });
    }
);
