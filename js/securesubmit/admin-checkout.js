AdminOrder.prototype._secureSubmitOldSubmit = AdminOrder.prototype.submit;
Object.extend(AdminOrder.prototype, {
    submit: function() {
        if (this.paymentMethod != 'hps_securesubmit') {
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

            if (this.orderItemChanged) {
                if (confirm('You have item changes')) {
                    if (editForm.submit()) {
                        disableElements('save');
                    }
                } else {
                    this.itemsUpdate();
                }
            } else {
                if (editForm.submit()) {
                    disableElements('save');
                }
            }
        } else {
            alert('Unexpected error.')
        }
    }
});