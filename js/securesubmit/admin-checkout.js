AdminOrder.prototype.__secureSubmitOldSubmit = AdminOrder.prototype.submit;
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
            // Set credit card information
            var creditCardId = $('hps_securesubmit_stored_card_select').value;
            if (customerStoredCards[creditCardId]) {
                var creditCardData = customerStoredCards[creditCardId];
                $('hps_securesubmit_expiration').value = parseInt(creditCardData.cc_exp_month);
                $('hps_securesubmit_expiration_yr').value = creditCardData.cc_exp_year;
                $('hps_securesubmit_token').value = creditCardData.token_value;
                $('hps_securesubmit_cc_last_four').value = creditCardData.cc_last_four;
                this.secureSubmitResponseHandler({
                    token_value:  creditCardData.token_value,
                    token_type:   null, // 'supt'?                                                                                                                                                                                      
                    token_expire: new Date().toISOString(),
                    card: {
                        number: creditCardData.cc_last_four
                    }
                });
            }
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