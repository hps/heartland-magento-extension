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
                    lastFourField = $('hps_securesubmit_cc_last_four');
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
                    alert('Unexpected error.')
                }
            }
        });

        payment.secureSubmitPublicKey = '<?php echo $public_key ?>';
        payment.secureSubmitGetTokenDataUrl = '<?php echo Mage::getUrl('securesubmit/storedcard/getTokenData', array('_secure' => TRUE)) ?>';
    }
    else if( !document.getElementById('multishipping-billing-form').empty() ){

        secureSubmit = multishipping(document.getElementById('multishipping-billing-form'));
        console.log(secureSubmit);
        secureSubmit.secureSubmitPublicKey = '<?php echo $public_key ?>';
        secureSubmit.secureSubmitGetTokenDataUrl = '<?php echo Mage::getUrl('securesubmit/storedcard/getTokenData', array('_secure' => TRUE)) ?>';

        document.addEventListener('DOMContentLoaded', function() {
            Event.observe('payment-continue', 'click', function(e){ Event.stop(e); secureSubmit.save(); });
        });
    }

    function multishipping(multiForm){
        var secureSubmit = {
            save: function() {
                if (payment.currentMethod != 'hps_securesubmit') {
                    multiForm.submit();
                    return;
                }

                document.getElementById('payment-continue').disable();

                // Use stored card checked, get existing token data
                if (this.secureSubmitUseStoredCard()) {
                    var storedcardId = $('hps_securesubmit_stored_card_select').value;

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

                        hps.tokenize({
                            data: {
                                public_key: this.secureSubmitPublicKey,
                                number: $('hps_securesubmit_cc_number').value,
                                cvc: $('hps_securesubmit_cc_cid').value,
                                exp_month: $('hps_securesubmit_expiration').value,
                                exp_year: $('hps_securesubmit_expiration_yr').value
                            },
                            success: this.secureSubmitResponseHandler.bind(this),
                            error: this.secureSubmitResponseHandler.bind(this),
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
                    lastFourField = $('hps_securesubmit_cc_last_four');
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
                    alert('Unexpected error.')
                }
            }
        };
        return secureSubmit;
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
                    });;
                } else {
                    alert('Unexpected error.')
                }
            }
        });
    }
});