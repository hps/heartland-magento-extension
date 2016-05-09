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
                var validator = new Validation(multiForm);
                if (validator.validate()) {
                  var date = $('hps_securesubmit_exp_date').value.split('/');
                  var hps_securesubmit_expiration = date[0].trim();
                  var hps_securesubmit_expiration_yr = date[1].trim();

                    hps.tokenize({
                        data: {
                            public_key: this.secureSubmitPublicKey,
                            number: $('hps_securesubmit_cc_number').value,
                            cvc: $('hps_securesubmit_cc_cid').value,
                            exp_month: hps_securesubmit_expiration,
                            exp_year: hps_securesubmit_expiration_yr
                        },
                        success: this.secureSubmitResponseHandler.bind(this),
                        error: this.secureSubmitResponseHandler.bind(this),
                    });
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

            if (response && response.error) {
                if (response.message) {
                    alert(response.message);
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
    if (typeof Payment != "undefined") {
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
                    var validator = new Validation(this.form);
                    if (this.validate() && validator.validate()) {
                        checkout.setLoadWaiting('payment');
                        var date = $('hps_securesubmit_exp_date').value.split('/');
                        var hps_securesubmit_expiration = date[0].trim();
                        var hps_securesubmit_expiration_yr = date[1].trim();

                        hps.tokenize({
                            data: {
                                public_key: this.secureSubmitPublicKey,
                                number: $('hps_securesubmit_cc_number').value,
                                cvc: $('hps_securesubmit_cc_cid').value,
                                exp_month: hps_securesubmit_expiration,
                                exp_year: hps_securesubmit_expiration_yr
                            },
                            success: this.secureSubmitResponseHandler.bind(this),
                            error: this.secureSubmitResponseHandler.bind(this)
                        });
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

                if (response && response.error) {
                    if (response.message) {
                        alert(response.message);
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

    if (typeof OPC != "undefined") {
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
                hps.tokenize({
                    data: {
                        public_key: this.secureSubmitPublicKey,
                        number: $('hps_securesubmit_cc_number').value,
                        cvc: $('hps_securesubmit_cc_cid').value,
                        exp_month: $('hps_securesubmit_expiration').value,
                        exp_year: $('hps_securesubmit_expiration_yr').value
                    },
                    success: this.secureSubmitResponseHandler.bind(this),
                    error: this.secureSubmitResponseHandler.bind(this)
                });
            },
            secureSubmitResponseHandler: function (response) {
                var tokenField = $('hps_securesubmit_token'),
                    lastFourField = $('hps_securesubmit_cc_last_four');
                var date = $('hps_securesubmit_exp_date').value.split('/');
                $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                $('hps_securesubmit_cc_exp_year').value = date[1].trim();
                tokenField.value = lastFourField.value = null;

                if (response && response.error) {
                    if (response.message) {
                        alert(response.message);
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
                    hps.tokenize({
                        data: {
                            public_key: window.payment.secureSubmitPublicKeyOSC,
                            number: $('hps_securesubmit_cc_number').value,
                            cvc: $('hps_securesubmit_cc_cid').value,
                            exp_month: $('hps_securesubmit_expiration').value,
                            exp_year: $('hps_securesubmit_expiration_yr').value
                        },
                        success: function(response){
                            secureSubmitResponseHandlerOSC(response, btn);
                        },
                        error: function(response){
                            secureSubmitResponseHandlerOSC(response, btn);
                        }
                    });
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

            if (response && response.error) {
                if (response.message) {
                    alert(response.message);
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
              hps.tokenize({
                  data: {
                      public_key: this.secureSubmitPublicKey,
                      number: $('hps_securesubmit_cc_number').value,
                      cvc: $('hps_securesubmit_cc_cid').value,
                      exp_month: $('hps_securesubmit_expiration').value,
                      exp_year: $('hps_securesubmit_expiration_yr').value
                  },
                  success: this.secureSubmitResponseHandler.bind(this),
                  error: this.secureSubmitResponseHandler.bind(this)
              });
          },
          secureSubmitResponseHandler: function (response) {
              var tokenField = $('hps_securesubmit_token'),
                  lastFourField = $('hps_securesubmit_cc_last_four');
              tokenField.value = lastFourField.value = null;

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
