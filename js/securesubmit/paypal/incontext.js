/*global window,document,alert,$$,Ajax,Validation,Form,Payment,checkout,payment,hps,paypal*/
(function (document, window) {
    var PAYMENT_CONTINUE_BUTTON_ID = 'hps-paypal-save-button';

    function getAnchor(el) {
        if (el.href) {
            return el;
        }

        return getAnchor(el.parentNode);
    }

    function getUrl(e, buttons, config) {
        var url = '';

        if (buttons.length === 1 && buttons[0] === PAYMENT_CONTINUE_BUTTON_ID) {
            if (payment.currentMethod === 'hps_paypal_credit') {
                url = config.bmlUrl;
            } else {
                url = config.stdUrl;
            }
        } else {
            if (getAnchor(e.target).href.indexOf('credit') !== -1) {
                url = config.bmlUrl;
            } else {
                url = config.stdUrl;
            }
        }

        return url;
    }

    function setPaymentCallbacks() {
        if (typeof Payment === 'undefined') {
            return;
        }

        Payment.prototype.save = Payment.prototype.save.wrap(function (save) {
            var validator = new Validation(this.form);
            if (this.validate() && validator.validate()) {
                if (payment.currentMethod.indexOf('hps_paypal') === 0) {
                    var request = new Ajax.Request(this.saveUrl, {
                        method: 'post',
                        onComplete: function () {},
                        onSuccess: function () {},
                        onFailure: checkout.ajaxFailure.bind(checkout),
                        parameters: Form.serialize(this.form)
                    });
                } else {
                    save(); //return default method
                }
            }
        });
    }

    function checkoutReady() {
        var config = hps.paypal.incontext;
        var buttons = [];
        var request;
        var url;

        $$('[id^="hps_shortcut_"]').each(function (el) {
            buttons.push(el);
        });

        if (buttons && buttons.length === 0) {
            if ($(PAYMENT_CONTINUE_BUTTON_ID) !== null) {
                buttons.push(PAYMENT_CONTINUE_BUTTON_ID);
            } else {
                paypal.checkout.closeFlow();
                return;
            }
        }

        paypal.checkout.setup('undefined', {
            environment: config.env,
            button: buttons,
            click: function (e) {
                if (typeof payment !== 'undefined' && payment.currentMethod.indexOf('hps_paypal') === -1) {
                    return;
                }
                e.preventDefault();
                paypal.checkout.initXO();

                url = getUrl(e, buttons, config);
                request = new Ajax.Request(url, {
                    onSuccess: function (response) {                        
                        var resp = JSON.parse(response.responseText); 
                        if (resp.result === 'error' && resp.redirect) {
                            window.location.href = resp.redirect;
                        }
                        if (resp.result === 'error') {
                            checkout.ajaxFailure.bind(checkout);
                        }
                        paypal.checkout.startFlow(response.responseText);
                    },
                    onFailure: function (response) {
                        alert(response.responseText);
                        paypal.checkout.closeFlow();
                    }
                });
            }
        });
    }

    window.secureSubmitPayPalIncontext = function () {
        window.paypalCheckoutReady = checkoutReady;
        setPaymentCallbacks();
    };

    document.observe('dom:loaded', window.secureSubmitPayPalIncontext);
}(document, window));
