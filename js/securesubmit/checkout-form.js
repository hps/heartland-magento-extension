function securesubmitMultishipping(multiForm) {
    var secureSubmit = {
        save: function() {
            if (payment && payment.currentMethod != 'hps_securesubmit') {
                multiForm.submit();
                return;
            }

            document.getElementById('payment-continue').disable();

            // Use stored card checked, get existing token data
            if (this.secureSubmitUseStoredCard()) {
                var storedcardId = $$('[name="hps_securesubmit_stored_card_select"]:checked')[0].value;

                new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
                    method: 'post',
                    parameters: {storedcard_id: storedcardId},
                    onSuccess: function(response) {
                        var data = response.responseJSON;
                        if (data && data.token) {
                            $('hps_securesubmit_cc_exp_month').value = parseInt(data.token.cc_exp_month);
                            $('hps_securesubmit_cc_exp_year').value = data.token.cc_exp_year;
                        }
                        this.secureSubmitResponseHandler({
                            token_value:  data.token.token_value,
                            token_type:   null, // 'supt'?
                            token_expire: new Date().toISOString(),
                            card:         {
                                number: data.token.cc_last4
                            }
                        });
                    }.bind(this),
                    onFailure: function() {
                        alert('Unknown error. Please try again.');
                    }
                });
            }
            // Use stored card not checked, get new token
            else {
                if (SecureSubmitMagento.options.useIframes) {
                    SecureSubmitMagento.hps.Messages.post({
                        accumulateData: true,
                        action: 'tokenize',
                        message: SecureSubmitMagento.options.publicKey
                    }, 'cardNumber');
                } else {
                    var validator = new Validation(multiForm);
                    if (validator.validate()) {
                        var date = $('hps_securesubmit_exp_date').value.split('/');
                        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                        (new Heartland.HPS({
                            publicKey: this.secureSubmitPublicKey,
                            cardNumber: $('hps_securesubmit_cc_number').value,
                            cardCvv: $('hps_securesubmit_cvv_number').value,
                            cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                            cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                            success: this.secureSubmitResponseHandler.bind(this),
                            error: this.secureSubmitResponseHandler.bind(this)
                        })).tokenize();
                    }
                }
            }
        },
        secureSubmitUseStoredCard: function () {
            var newRadio = $('hps_securesubmit_stored_card_select_new');
            return !newRadio.checked;
        },
        secureSubmitResponseHandler: function (response) {
            var tokenField = $('hps_securesubmit_token'),
                lastFourField = $('hps_securesubmit_cc_last_four');
            var date = $('hps_securesubmit_exp_date').value.split('/');
            $('hps_securesubmit_cc_exp_month').value = date[0].trim();
            $('hps_securesubmit_cc_exp_year').value = date[1].trim();
            tokenField.value = lastFourField.value = null;

            if (SecureSubmitMagento.skipCreditCard) {
                SecureSubmitMagento.completeCheckout();
                return;
            }

            if (response && response.error) {
                if (response.error.message) {
                    alert(response.error.message);
                }
            } else if (response && response.token_value) {
                tokenField.value = response.token_value;
                lastFourField.value = response.card.number.substr(-4);

                // Continue Magento checkout steps
                document.getElementById('payment-continue').enable();
                multiForm.submit();
            } else {
                alert('Unexpected error.');
            }
        }
    };
    return secureSubmit;
}

document.observe('dom:loaded', function () {
    // Override default Payment save handler
    if (typeof Payment != 'undefined') {
        if (typeof Payment.prototype._secureSubmitOldSave === 'undefined') {
            var oldPayment = Object.clone(Payment.prototype);
            Payment.prototype._secureSubmitOldSave = oldPayment.save;
        }
        Object.extend(Payment.prototype, {
            save: function() {
                if (this.currentMethod != 'hps_securesubmit') {
                    this._secureSubmitOldSave();
                    return;
                }

                if (checkout.loadWaiting !== false) return;

                // Use stored card checked, get existing token data
                if (this.secureSubmitUseStoredCard()) {
                    var storedcardId = $$('[name="hps_securesubmit_stored_card_select"]:checked')[0].value;
                    checkout.setLoadWaiting('payment');
                    new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
                        method: 'post',
                        parameters: {storedcard_id: storedcardId},
                        onSuccess: function(response) {
                            var data = response.responseJSON;
                            if (data && data.token) {
                                $('hps_securesubmit_cc_exp_month').value = parseInt(data.token.cc_exp_month);
                                $('hps_securesubmit_cc_exp_year').value = data.token.cc_exp_year;
                            }
                            this.secureSubmitResponseHandler({
                                token_value:  data.token.token_value,
                                token_type:   null, // 'supt'?
                                token_expire: new Date().toISOString(),
                                card:         {
                                    number: data.token.cc_last4
                                }
                            });
                        }.bind(this),
                        onFailure: function() {
                            alert('Unknown error. Please try again.');
                            checkout.setLoadWaiting(false);
                        }
                    });
                }
                // Use stored card not checked, get new token
                else {
                    if (SecureSubmitMagento.options.useIframes) {
                        checkout.setLoadWaiting('payment');
                        SecureSubmitMagento.hps.Messages.post({
                            accumulateData: true,
                            action: 'tokenize',
                            message: SecureSubmitMagento.options.publicKey
                        }, 'cardNumber');
                    } else {
                        var validator = new Validation(this.form);
                        if (this.validate() && validator.validate()) {
                            checkout.setLoadWaiting('payment');
                            var date = $('hps_securesubmit_exp_date').value.split('/');
                            $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                            $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                            (new Heartland.HPS({
                                publicKey: this.secureSubmitPublicKey,
                                cardNumber: $('hps_securesubmit_cc_number').value,
                                cardCvv: $('hps_securesubmit_cvv_number').value,
                                cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                                cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                                success: this.secureSubmitResponseHandler.bind(this),
                                error: this.secureSubmitResponseHandler.bind(this)
                            })).tokenize();
                        }
                    }
                }
            },
            secureSubmitUseStoredCard: function () {
                var newRadio = $('hps_securesubmit_stored_card_select_new');
                return !newRadio.checked;
            },
            secureSubmitResponseHandler: function (response) {
                var tokenField = $('hps_securesubmit_token'),
                    lastFourField = $('hps_securesubmit_cc_last_four');
                var date = $('hps_securesubmit_exp_date').value.split('/');
                $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                tokenField.value = lastFourField.value = null;

                if (SecureSubmitMagento.skipCreditCard) {
                    SecureSubmitMagento.completeCheckout();
                    return;
                }

                if (response && response.error) {
                    if (response.error.message) {
                        alert(response.error.message);
                    }
                    checkout.setLoadWaiting(false);
                } else if (response && response.token_value) {
                    tokenField.value = response.token_value;
                    lastFourField.value = response.card.number.substr(-4);

                    // Continue Magento checkout steps
                    new Ajax.Request(this.saveUrl, {
                        method:'post',
                        onComplete: this.onComplete,
                        onSuccess: this.onSave,
                        onFailure: checkout.ajaxFailure.bind(checkout),
                        parameters: Form.serialize(this.form)
                    });
                } else {
                    alert('Unexpected error.');
                }
            }
        });
    }

    if (typeof OPC != 'undefined') {
        if (typeof OPC.prototype._secureSubmitOldSubmit === 'undefined') {
            var oldOPC = Object.clone(OPC.prototype);
            OPC.prototype._secureSubmitOldSubmit = oldOPC.submit;
        }
        Object.extend(OPC.prototype, {
            save: function() {
                if (this.sectionsToValidate[0].currentMethod != 'hps_securesubmit') {
                    this._secureSubmitOldSubmit();
                    return;
                }
                if (SecureSubmitMagento.options.useIframes) {
                    SecureSubmitMagento.hps.Messages.post({
                        accumulateData: true,
                        action: 'tokenize',
                        message: SecureSubmitMagento.options.publicKey
                    }, 'cardNumber');
                } else {
                    var date = $('hps_securesubmit_exp_date').value.split('/');
                    $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                    $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                    (new Heartland.HPS({
                        publicKey: this.secureSubmitPublicKey,
                        cardNumber: $('hps_securesubmit_cc_number').value,
                        cardCvv: $('hps_securesubmit_cvv_number').value,
                        cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                        cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                        success: this.secureSubmitResponseHandler.bind(this),
                        error: this.secureSubmitResponseHandler.bind(this)
                    })).tokenize();
                }
            },
            secureSubmitResponseHandler: function (response) {
                var tokenField = $('hps_securesubmit_token'),
                    lastFourField = $('hps_securesubmit_cc_last_four');
                var date = $('hps_securesubmit_exp_date').value.split('/');
                $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                tokenField.value = lastFourField.value = null;

                if (SecureSubmitMagento.skipCreditCard) {
                    SecureSubmitMagento.completeCheckout();
                    return;
                }

                if (response && response.error) {
                    if (response.error.message) {
                        alert(response.error.message);
                    }
                    checkout.setLoadWaiting(false);
                } else if (response && response.token_value) {
                    tokenField.value = response.token_value;
                    lastFourField.value = response.card.number.substr(-4);

                    this.setLoadWaiting(true);
                    var params = Form.serialize(this.form);
                    var request = new Ajax.Request(this.saveUrl, {
                        method: 'post',
                        parameters: params,
                        onSuccess: this.setResponse.bind(this),
                        onFailure: this.ajaxFailure.bind(this)
                    });
                } else {
                    alert('Unexpected error.');
                }
            }
        });
    }

    // MageStore One Step Checkout
    if (typeof oscPlaceOrder == 'function') {
        var cloneFunction = function (that) {
            var temp = function temporary() { return that.apply(this, arguments); };
            for (var key in this) {
                if (this.hasOwnProperty(key)) {
                    temp[key] = this[key];
                }
            }
            return temp;
        };
        var oscPlaceOrderOriginal = cloneFunction(oscPlaceOrder);
        oscPlaceOrder = function (btn) {
            var validator = new Validation('one-step-checkout-form');
            var form = $('one-step-checkout-form');
            if (validator.validate()) {
                var currentPayment = $RF(form, 'payment[method]');
                if (currentPayment!='hps_securesubmit') {
                    oscPlaceOrderOriginal(btn);
                    return;
                }
                $('onestepcheckout-place-order-loading').hide();
                $('onestepcheckout-button-place-order').removeClassName('place-order-loader');
                $('onestepcheckout-button-place-order').addClassName('onestepcheckout-btn-checkout');
                if (secureSubmitUseStoredCardOSC()) {
                    var storedcardId = $('hps_securesubmit_stored_card_select').value;
                    new Ajax.Request(window.payment.secureSubmitGetTokenDataUrlOSC, {
                      method: 'post',
                      parameters: {storedcard_id: storedcardId},
                      onSuccess: function (response) {
                          var data = response.responseJSON;
                          if (data && data.token) {
                              $('hps_securesubmit_expiration').value = parseInt(data.token.cc_exp_month);
                              $('hps_securesubmit_expiration_yr').value = data.token.cc_exp_year;
                          }
                          secureSubmitResponseHandlerOSC({
                              token_value:  data.token.token_value,
                              token_type:   null, // 'supt'?
                              token_expire: new Date().toISOString(),
                              card: {
                                  number: data.token.cc_last4
                              }
                          }, btn);
                      },
                      onFailure: function() {
                          alert('Unknown error. Please try again.');
                          $('onestepcheckout-place-order-loading').show();
                          $('onestepcheckout-button-place-order').removeClassName('onestepcheckout-btn-checkout');
                          $('onestepcheckout-button-place-order').addClassName('place-order-loader');
                      }
                    });
                } else {
                    if (SecureSubmitMagento.options.useIframes) {
                        SecureSubmitMagento.hps.Messages.post({
                            accumulateData: true,
                            action: 'tokenize',
                            message: SecureSubmitMagento.options.publicKey
                        }, 'cardNumber');
                    } else {
                        var date = $('hps_securesubmit_exp_date').value.split('/');
                        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                        (new Heartland.HPS({
                            publicKey: window.payment.secureSubmitPublicKeyOSC,
                            cardNumber: $('hps_securesubmit_cc_number').value,
                            cardCvv: $('hps_securesubmit_cvv_number').value,
                            cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                            cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                            success: function (response) {
                                secureSubmitResponseHandlerOSC(response, btn);
                            },
                            error: function (response) {
                                secureSubmitResponseHandlerOSC(response, btn);
                            }
                        })).tokenize();
                    }
                }
            }
        };

        secureSubmitUseStoredCardOSC = function () {
            var storedCheckbox = $('hps_securesubmit_stored_card_checkbox');
            return storedCheckbox && storedCheckbox.checked;
        };

        secureSubmitResponseHandlerOSC = function (response, btn) {
            var tokenField = $('hps_securesubmit_token'),
                lastFourField = $('hps_securesubmit_cc_last_four');
            tokenField.value = lastFourField.value = null;

            if (SecureSubmitMagento.skipCreditCard) {
                SecureSubmitMagento.completeCheckout();
                return;
            }

            if (response && response.error) {
                if (response.error.message) {
                    alert(response.error.message);
                }

                $('onestepcheckout-place-order-loading').hide();
                $('onestepcheckout-button-place-order').removeClassName('place-order-loader');
                $('onestepcheckout-button-place-order').addClassName('onestepcheckout-btn-checkout');
            } else if (response && response.token_value) {
                tokenField.value = response.token_value;
                lastFourField.value = response.card.number.substr(-4);

                $('onestepcheckout-place-order-loading').show();
                $('onestepcheckout-button-place-order').removeClassName('onestepcheckout-btn-checkout');
                $('onestepcheckout-button-place-order').addClassName('place-order-loader');
                oscPlaceOrderOriginal(btn);
            } else {
                alert('Unexpected error.');
                $('onestepcheckout-place-order-loading').show();
                $('onestepcheckout-button-place-order').removeClassName('onestepcheckout-btn-checkout');
                $('onestepcheckout-button-place-order').addClassName('place-order-loader');
            }
        };
    }

    // IWD OPC
    if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
        if (typeof IWD.OPC._secureSubmitOldSavePayment === 'undefined') {
            var oldOPC = Object.clone(IWD.OPC);
            IWD.OPC._secureSubmitOldSavePayment = oldOPC.savePayment;
        }
        Object.extend(IWD.OPC, {
            savePayment: function() {
                if (payment.currentMethod != 'hps_securesubmit') {
                    this._secureSubmitOldSavePayment();
                    return;
                }

                if (!this.saveOrderStatus) {
                    return;
                }

                if (SecureSubmitMagento.options.useIframes) {
                    SecureSubmitMagento.hps.Messages.post({
                        accumulateData: true,
                        action: 'tokenize',
                        message: SecureSubmitMagento.options.publicKey
                    }, 'cardNumber');
                } else {
                    var date = $('hps_securesubmit_exp_date').value.split('/');
                    $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                    $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                    (new Heartland.HPS({
                        publicKey: this.secureSubmitPublicKey,
                        cardNumber: $('hps_securesubmit_cc_number').value,
                        cardCvv: $('hps_securesubmit_cvv_number').value,
                        cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                        cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                        success: this.secureSubmitResponseHandler.bind(this),
                        error: this.secureSubmitResponseHandler.bind(this)
                    })).tokenize();
                }
            },
            secureSubmitResponseHandler: function (response) {
                var tokenField = $('hps_securesubmit_token'),
                    lastFourField = $('hps_securesubmit_cc_last_four');
                tokenField.value = lastFourField.value = null;

                if (SecureSubmitMagento.skipCreditCard) {
                    SecureSubmitMagento.completeCheckout();
                    return;
                }

                if (response && response.error) {
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.xhr = null;
                    IWD.OPC.Checkout.unlockPlaceOrder();
                    alert(response.error.message);
                } else if (response && response.token_value) {
                    tokenField.value = response.token_value;
                    lastFourField.value = response.card.number.substr(-4);

                    var form = $j_opc('#co-payment-form').serializeArray();
                    IWD.OPC.Checkout.xhr = $j_opc.post(
                        IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',
                        form,
                        IWD.OPC.preparePaymentResponse,
                        'json'
                    );
                } else {
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.xhr = null;
                    IWD.OPC.Checkout.unlockPlaceOrder();
                    alert('Unexpected error.');
                }
            }
        });
    }
});

(function (window, document, undefined) {
    var THIS = {
        skipCreditCard: false,
        init: function (options) {
            THIS.options = options;
            THIS.observeSavedCards();
            THIS.observeGift();

            if (typeof Payment !== 'undefined') {
                window.payment = window.payment || {};
                payment.secureSubmitPublicKey = THIS.options.publicKey;
                payment.secureSubmitGetTokenDataUrl = THIS.options.tokenDataUrl;
            } else if (!document.getElementById('multishipping-billing-form').empty()){
                THIS.secureSubmitMS = securesubmitMultishipping(document.getElementById('multishipping-billing-form'));
                THIS.secureSubmitMS.secureSubmitPublicKey = THIS.options.publicKey;
                THIS.secureSubmitMS.secureSubmitGetTokenDataUrl = THIS.options.tokenDataUrl;
                document.observe('dom:loaded', function() {
                    Event.observe('payment-continue', 'click', function(e){ Event.stop(e); THIS.secureSubmitMS.save(); });
                });
            }

            if (typeof OPC !== 'undefined') {
                OPC.prototype.secureSubmitPublicKey = THIS.options.publicKey;
                OPC.prototype.secureSubmitGetTokenDataUrl = THIS.options.tokenDataUrl;
            }

            // MageStore OSC
            window.payment = window.payment || {};
            window.payment.secureSubmitPublicKeyOSC = THIS.options.publicKey;
            window.payment.secureSubmitGetTokenDataUrlOSC = THIS.options.tokenDataUrl;

            // IWD OPC
            if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
                IWD.OPC.secureSubmitPublicKey = THIS.options.publicKey;
                IWD.OPC.secureSubmitGetTokenDataUrl = THIS.options.tokenDataUrl;
            }

            THIS.setupFields();
        },
        observeSavedCards: function () {
            if (THIS.options.loggedIn && THIS.options.allowCardSaving) {
                $$('[name="' + THIS.options.code + '_stored_card_select"]').each(function (el) {
                    $(el).observe('click', function() {
                        if ($(THIS.options.code + '_stored_card_select_new').checked) {
                            $(THIS.options.code + '_cc_form').show();
                        } else {
                            $(THIS.options.code + '_cc_form').hide();
                        }

                        if (!THIS.options.useIframes) {
                            $(THIS.options.code + '_cc_number').toggleClassName('validate-cc-number');
                        }

                        $$('[name="' + THIS.options.code + '_stored_card_select"]').each(function (element) {
                            $(element).up(2).removeClassName('active');
                        });

                        $(el).up(2).addClassName('active');
                    });
                });
            }
        },
        observeGift: function () {
            if (THIS.options.allowGift) {
                Event.observe('apply-gift-card', 'click', function(event) {
                    $j.ajax({
                        url: THIS.options.giftBalanceUrl,
                        type: 'GET',
                        data: "giftcard_number=" + $j('#' + THIS.options.code + '_giftcard_number').val()
                            + "&giftcard_pin=" + $j('#' + THIS.options.code + '_giftcard_pin').val(),
                        success: function(data) {
                            if (data.error) {
                                alert('Error adding gift card: ' + data.message);
                            } else {
                                //successful gift, show things
                                $j('#apply-gift-card').hide();
                                $j('#' + THIS.options.code + '_giftcard_number').hide();
                                $j('#' + THIS.options.code + '_giftcard_pin').hide();
                                $j('#gift-card-number-label').text($j('#' + THIS.options.code + '_giftcard_number').val() + ' - $' + data.balance);
                                $j('#gift-card-number-label').show();
                                $j('#remove-gift-card').show();

                                if (!data.less_than_total) {
                                    // skip cc capture enable
                                    $$('#payment_form_hps_securesubmit .new-card')[0].hide();
                                    $('hps_securesubmit_gift_card').style.borderTopWidth = '0px';
                                    $(THIS.options.code + '_token').value = 'dummy';
                                    THIS.skipCreditCard = true;
                                    $(THIS.options.code + '_giftcard_skip_cc').value = 'true';
                                }
                            }
                        }
                    });
                });
                Event.observe('remove-gift-card', 'click', function(event) {
                    $j('#apply-gift-card').show();
                    $j('#' + THIS.options.code + '_giftcard_number').val('');
                    $j('#' + THIS.options.code + '_giftcard_number').show();
                    $j('#' + THIS.options.code + '_giftcard_pin').val('');
                    $j('#' + THIS.options.code + '_giftcard_pin').show();
                    $j('#gift-card-number-label').text('');
                    $j('#gift-card-number-label').hide();
                    $j('#remove-gift-card').hide();

                    // skip cc capture disable
                    $$('#payment_form_hps_securesubmit .new-card')[0].show();
                    $('hps_securesubmit_gift_card').style.borderTopWidth = '1px';
                    $(THIS.options.code + '_token').value = '';
                    THIS.skipCreditCard = false;
                    $(THIS.options.code + '_giftcard_skip_cc').value = 'false';
                });
            }
        },
        setupFields: function () {
            if (THIS.options.useIframes) {
                THIS.hps = new Heartland.HPS({
                    publicKey: THIS.options.publicKey,
                    type:      'iframe',
                    fields: {
                        cardNumber: {
                            target:      THIS.options.iframeTargets.cardNumber,
                            placeholder: '•••• •••• •••• ••••'
                        },
                        cardExpiration: {
                            target:      THIS.options.iframeTargets.cardExpiration,
                            placeholder: 'MM / YYYY'
                        },
                        cardCvv: {
                            target:      THIS.options.iframeTargets.cardCvv,
                            placeholder: 'CVV'
                        }
                    },
                    style: {
                        '#heartland-field': {
                             'height': '40px',
                             'border-radius': '0px',
                             'border': '1px solid silver',
                             'letter-spacing': '2.5px',
                             'margin': '5px 0px 15px 0px',
                             'max-width': '365px',
                             'width': '100%',
                             'padding-left': '9px',
                             'font-size': '15px'
                         },
                        '@media only screen and (max-width: 479px)': {
                            '#heartland-field': {
                                'width': '95%'
                            }
                        }
                    },
                    onTokenSuccess: function (resp) {
                        $(THIS.options.code + '_token').value = resp.token_value;
                        $(THIS.options.code + '_cc_last_four').value = resp.card.number.substr(-4);
                        $(THIS.options.code + '_cc_type').value = resp.card_type;
                        $(THIS.options.code + '_cc_exp_month').value = resp.exp_month;
                        $(THIS.options.code + '_cc_exp_year').value = resp.exp_year;

                        THIS.completeCheckout();
                    },
                    onTokenError: function (response) {
                        if (THIS.skipCreditCard) {
                            THIS.completeCheckout();
                            return;
                        }

                        if (response.error.message) {
                            alert(response.error.message);
                        } else {
                            alert('Unexpected error.');
                        }

                        if (typeof Payment !== 'undefined' && window.checkout) {
                            checkout.setLoadWaiting(false);
                        } else if (typeof OPC !== 'undefined' && window.checkout) {
                            checkout.setLoadWaiting(false);
                        }
                    }
                });
            } else {
                Heartland.Card.attachNumberEvents('#' + THIS.options.code + '_cc_number');
                Heartland.Card.attachExpirationEvents('#' + THIS.options.code + '_exp_date');
                Heartland.Card.attachCvvEvents('#' + THIS.options.code + '_cvv_number');
            }
        },
        completeCheckout: function () {
            if (typeof OPC !== 'undefined') {
                checkout.setLoadWaiting(true);
                var params = Form.serialize(checkout.form);
                var request = new Ajax.Request(checkout.saveUrl, {
                    method: 'post',
                    parameters: params,
                    onSuccess: checkout.setResponse.bind(checkout),
                    onFailure: checkout.ajaxFailure.bind(checkout)
                });
            } else if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
                var form = $j_opc('#co-payment-form').serializeArray();
                IWD.OPC.Checkout.xhr = $j_opc.post(
                    IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',
                    form,
                    IWD.OPC.preparePaymentResponse,
                    'json'
                );
            } else if (window.oscPlaceOrderOriginal) {
                $('onestepcheckout-place-order-loading').show();
                $('onestepcheckout-button-place-order').removeClassName('onestepcheckout-btn-checkout');
                $('onestepcheckout-button-place-order').addClassName('place-order-loader');
                oscPlaceOrderOriginal(btn);
            } else if (typeof Payment !== 'undefined') {
                var params = Form.serialize(payment.form);
                var request = new Ajax.Request(payment.saveUrl, {
                    method: 'post',
                    parameters: params,
                    onComplete: payment.onComplete,
                    onSuccess: payment.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout)
                });
            } else if (!document.getElementById('multishipping-billing-form').empty()) {
                document.getElementById('payment-continue').enable();
                document.getElementById('multishipping-billing-form').submit();
            }
        }
    };
    window.SecureSubmitMagento = THIS;
}(window, window.document));
