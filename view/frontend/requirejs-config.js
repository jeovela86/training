var config = {
    map:{
        '*': {
            'openpay-data.v1.min.colombia':'WolfSellers_PaymentLink/js/openpay/colombia/openpay-data.v1.min',
            paymentlinkOpenpayForm: 'WolfSellers_PaymentLink/js/form/paymentlink-openpay-form',
            paymentMethodChange: 'WolfSellers_PaymentLink/js/form/payment-method-change',
            paymentlinkUpdateCVV: 'WolfSellers_PaymentLink/js/form/payment-method-update-cvv'
        }
    },
    shim: {
        'WolfSellers_PaymentLink/js/openpay/colombia/openpay-data.v1.min': {
            deps: [
                'WolfSellers_PaymentLink/js/openpay/colombia/openpay.v1.min'
            ]
        }
    }
};
