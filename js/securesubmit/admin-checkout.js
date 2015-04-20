AdminOrder.prototype._secureSubmitOldSubmit = AdminOrder.prototype.submit;
Object.extend(AdminOrder.prototype, {
    submit: function() {
        if (this.paymentMethod != 'hps_securesubmit') {
            this._secureSubmitOldSubmit();
            return;
        }
        // Use stored card checked, get existing token data                                                                                                                                                                                 
        if (this.secureSubmitUseStoredCard()) {
            var storedcardId = $('hps_securesubmit_stored_card_select').value;
            var customerId = $('hps_securesubmit_customer_id').value;
            //checkout.setLoadWaiting('payment');                                                                                                                                                                                           
            new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
                method: 'post',
                parameters: {storedcard_id: storedcardId, customer_id: customerId},
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
                    //checkout.setLoadWaiting(false);                                                                                                                                                                                       
                }
            });
        }
        // Use stored card not checked, get new token                                                                                                                                                                                       
        else{
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
            //checkout.setLoadWaiting(false);
        } else if (response && response.token_value) {
            tokenField.value = response.token_value;
            lastFourField.value = response.card.number.substr(-4);

            if (this.orderItemChanged) {
                if (confirm('You have item changes')) {
                    if (editForm.submit()) {
                        disableElements('save');
                    }
                } else {
                    this.itemsUpdate();
                }
            } else {
                if(this.secureSubmitUseStoredCard()){
                    if (editForm._submit()) {
                        disableElements('save');
                    }
                }else{
                    if (editForm.submit()) {
                        disableElements('save');
                    }
                }
            }
        } else {
            alert('Unexpected error.')
        }
    }
});