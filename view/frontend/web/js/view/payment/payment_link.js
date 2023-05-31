define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'paymentlink',
                component: 'WolfSellers_PaymentLink/js/view/payment/method-renderer/paymentlink-method'
            }
        );
        return Component.extend({});
    }
);
