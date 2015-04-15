// Override default Payment save handler
Payment.prototype._secureSubmitOldSave = Payment.prototype.save;
Object.extend(Payment.prototype, {
    save: function() {
        if (this.currentMethod != 'hps_securesubmit') {
            this._secureSubmitOldSave();
            return;
        }

        if (checkout.loadWaiting != false) return;

        // Use stored card checked, get existing token data
        if (this.secureSubmitUseStoredCard()) {
            var storedcardId = $('hps_securesubmit_stored_card_select').value;
            checkout.setLoadWaiting('payment');
            new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
                method: 'post',
                parameters: {storedcard_id: storedcardId},
                onSuccess: function(response) {
                    var data = response.responseJSON;
                    if (data && data.token) {
                        $('hps_securesubmit_expiration').value = parseInt(data.token.cc_exp_month);
                        $('hps_securesubmit_expiration_yr').value = data.token.cc_exp_year;
                    }
                    this.secureSubmitResponseHandler({
                        token_value:  data.token.token_value,
                        token_type:   null, // 'supt'?
                        token_expire: new Date().toISOString(),
                        card:         {
                            number: data.token.cc_last4,
                            type: data.token.cc_type
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
            }
        }
    },
    secureSubmitUseStoredCard: function () {
        var storedCheckbox = $('hps_securesubmit_stored_card_checkbox');
        return storedCheckbox && storedCheckbox.checked;
    },
    secureSubmitResponseHandler: function (response) {
        var tokenField = $('hps_securesubmit_token'),
            lastFourField = $('hps_securesubmit_cc_last_four'),
            ccTypeField = $('hps_securesubmit_cc_type');
        tokenField.value = lastFourField.value = ccTypeField.value = null;

        if (response && response.error) {
            if (response.message) {
                alert(response.message);
            }
            checkout.setLoadWaiting(false);
        } else if (response && response.token_value) {
            tokenField.value = response.token_value;
            lastFourField.value = response.card.number.substr(-4);
            ccTypeField.value = response.card.type || this.getCcType($('hps_securesubmit_cc_number').value);

            // Continue Magento checkout steps
            new Ajax.Request(this.saveUrl, {
                method:'post',
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout),
                parameters: Form.serialize(this.form)
            });
        } else {
            alert('Unexpected error.')
        }
    },
    getCcType: function(value) {
        var ccMatchedType = null;
        Validation.creditCartTypes.each(function (pair) {
            if (pair.value[0] && value.match(pair.value[0])) {
                ccMatchedType = pair.key;
                throw $break;
            }
        });
        return ccMatchedType;
    }
});
