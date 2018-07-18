if (!String.prototype.trim) {
  String.prototype.trim = function() {
    return this.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
  };
}

(function(window, document, undefined) {
  var opcTokenSubmits = {};
  var THIS = {
    __data: {},
    skipCreditCard: false,
    init: function(options) {
      THIS.options = options;
      THIS.observeSavedCards();
      THIS.observeGift();

      if (typeof Payment !== 'undefined') {
        window.payment = window.payment || {};
        payment.secureSubmitPublicKey = THIS.options.publicKey;
        payment.secureSubmitGetTokenDataUrl = THIS.options.tokenDataUrl;
      } else if (document.getElementById('multishipping-billing-form')) {
        THIS.secureSubmitMS = securesubmitMultishipping(
          document.getElementById('multishipping-billing-form')
        );
        THIS.secureSubmitMS.secureSubmitPublicKey = THIS.options.publicKey;
        THIS.secureSubmitMS.secureSubmitGetTokenDataUrl =
          THIS.options.tokenDataUrl;
        document.observe('dom:loaded', function() {
          Event.observe('payment-continue', 'click', function(e) {
            Event.stop(e);
            THIS.secureSubmitMS.save();
          });
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

      // Latest Version of IWD One page Checkout
      if (
        typeof iwdOpcConfig !== 'undefined' &&
        typeof OnePage !== 'undefined' &&
        typeof PaymentMethod !== 'undefined'
      ) {
        PaymentMethod.prototype.secureSubmitPublicKey = THIS.options.publicKey;
        PaymentMethod.prototype.secureSubmitGetTokenDataUrl =
          THIS.options.tokenDataUrl;
      }

      // AheadWorks OneStepCheckout
      if (typeof AWOnestepcheckoutForm !== 'undefined') {
        AWOnestepcheckoutForm.prototype.secureSubmitPublicKey =
          THIS.options.publicKey;
        AWOnestepcheckoutForm.prototype.secureSubmitGetTokenDataUrl =
          THIS.options.tokenDataUrl;
      }

      THIS.setupFields();
    },
    observeSavedCards: function() {
      if (THIS.options.loggedIn && THIS.options.allowCardSaving) {
        $$('[name="' + THIS.options.code + '_stored_card_select"]').each(
          function(el) {
            $(el).observe('click', function() {
              if ($(THIS.options.code + '_stored_card_select_new').checked) {
                $(THIS.options.code + '_cc_form').show();
              } else {
                $(THIS.options.code + '_cc_form').hide();
              }

              if (!THIS.options.useIframes) {
                $(THIS.options.code + '_cc_number').toggleClassName(
                  'validate-cc-number'
                );
              }

              $$('[name="' + THIS.options.code + '_stored_card_select"]').each(
                function(element) {
                  $(element)
                    .up(2)
                    .removeClassName('active');
                }
              );

              $(el)
                .up(2)
                .addClassName('active');
            });
          }
        );
      }
    },
    observeGift: function() {
      if (THIS.options.allowGift) {
        Event.observe('apply-gift-card', 'click', function(event) {
          $j.ajax({
            url: THIS.options.giftBalanceUrl,
            type: 'GET',
            data:
              'giftcard_number=' +
              $j('#' + THIS.options.code + '_giftcard_number').val() +
              '&giftcard_pin=' +
              $j('#' + THIS.options.code + '_giftcard_pin').val(),
            success: function(data) {
              if (data.error) {
                alert('Error adding gift card: ' + data.message);
              } else {
                //successful gift, show things
                $j('#apply-gift-card').hide();
                $j('#' + THIS.options.code + '_giftcard_number').hide();
                $j('#' + THIS.options.code + '_giftcard_pin').hide();
                $j('#gift-card-number-label').text(
                  $j('#' + THIS.options.code + '_giftcard_number').val() +
                    ' - $' +
                    data.balance
                );
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
            },
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
    setupFields: function() {
      if (THIS.options.useIframes) {
        var options = {
          publicKey: THIS.options.publicKey,
          type: 'iframe',
          fields: {
            cardNumber: {
              target: THIS.options.iframeTargets.cardNumber,
              placeholder: '•••• •••• •••• ••••',
            },
            cardExpiration: {
              target: THIS.options.iframeTargets.cardExpiration,
              placeholder: 'MM / YYYY',
            },
            cardCvv: {
              target: THIS.options.iframeTargets.cardCvv,
              placeholder: 'CVV',
            },
          },
          style: {
            '#heartland-field': {
              height: '40px',
              border: '1px solid silver',
              'letter-spacing': '2.5px',
              width: '97.5%',
              'padding-left': '9px',
            },
            '.iwd-opc-index-index #heartland-field': {
              'max-width': '365px',
            },
            '#heartland-field:hover': {
              border: '1px solid #3989e3',
            },
            '#heartland-field:focus': {
              border: '1px solid #3989e3',
              'box-shadow': 'none',
              outline: 'none',
            },
            '#heartland-field[name="cardNumber"]': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-inputcard-blank@2x.png) no-repeat right',
              'background-size': '50px 30px',
            },
            '#heartland-field.valid.card-type-visa': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-visa@2x.png) no-repeat top right',
              'background-size': '75px 84px',
            },
            '#heartland-field.invalid.card-type-visa': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-visa@2x.png) no-repeat bottom right',
              'background-size': '75px 84px',
            },
            '#heartland-field[name="cardNumber"].invalid.card-type-discover': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-discover@2x.png) no-repeat right',
              'background-size': '70px 74px',
              'background-position-y': '-35px',
            },
            '#heartland-field[name="cardNumber"].valid.card-type-discover': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-discover@2x.png) no-repeat right',
              'background-size': '70px 74px',
              'background-position-y': '2px',
            },
            '#heartland-field[name="cardNumber"].invalid.card-type-amex': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-input-amex@2x.png) no-repeat center right',
              'background-size': '50px 55px',
            },
            '#heartland-field[name="cardNumber"].valid.card-type-amex': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-inputcard-amex@2x.png) no-repeat center right',
              'background-size': '50px 55px',
            },
            '#heartland-field[name="cardNumber"].invalid.card-type-jcb': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-jcb@2x.png) no-repeat right',
              'background-size': '75px 75px',
              'background-position-y': '10px -35px',
            },
            '#heartland-field[name="cardNumber"].valid.card-type-jcb': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-jcb@2x.png) no-repeat right',
              'background-size': '75px 76px',
              'background-position-y': '10px 2px',
            },
            '#heartland-field[name="cardNumber"].invalid.card-type-mastercard': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-mastercard@2x.png) no-repeat bottom right',
              'background-size': '71px',
              'background-position-y': '-35px',
            },
            '#heartland-field[name="cardNumber"].valid.card-type-mastercard': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/ss-saved-mastercard@2x.png) no-repeat top right',
              'background-size': '71px',
              'background-position-y': '3px',
            },
            '#heartland-field[name="cardCvv"]': {
              background:
                'transparent url(' +
                THIS.options.baseUrl.replace('/index.php', '') +
                'skin/frontend/base/default/securesubmit/images/cvv1.png) no-repeat right',
              'background-size': '50px 30px',
            },
            '@media only screen and (max-width: 479px)': {
              '#heartland-field': {
                width: '95%',
              },
            },
          },
          onTokenSuccess: function(resp) {
            var heartland = resp.heartland || resp;

            // BEGIN: AheadWorks OneStepCheckout fix
            // This is required in order to work around a limitation with AW OSC and our
            // iframes' `message` event handler. Because of how AW OSC refreshes the payment
            // multiple times, mutiple event handlers for `message` are added, so the
            // `onTokenSuccess` event that we receive is firing multiple times which also
            // submits the form multiple times, attempting to create multiple orders.
            if (
              THIS.isOnePageCheckout() &&
              typeof opcTokenSubmits[heartland.token_value] !== 'undefined'
            ) {
              return;
            }

            opcTokenSubmits[heartland.token_value] = true;
            // END: AheadWorks OneStepCheckout fix

            $(THIS.options.code + '_token').value = heartland.token_value;
            $(
              THIS.options.code + '_cc_last_four'
            ).value = heartland.card.number.substr(-4);
            $(THIS.options.code + '_cc_type').value = heartland.card_type;
            $(
              THIS.options.code + '_cc_exp_month'
            ).value = heartland.exp_month.trim();
            $(
              THIS.options.code + '_cc_exp_year'
            ).value = heartland.exp_year.trim();

            if (resp.cardinal) {
              var el = document.createElement('input');
              el.value = resp.cardinal.token_value;
              el.type = 'hidden';
              el.name = 'payment[cardinal_token]';
              el.id = THIS.options.code + '_cardinal_token';
              $('payment_form_' + THIS.options.code).appendChild(el);
            }

            THIS.initializeCCA(THIS.completeCheckout);
          },
          onTokenError: function(response) {
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
            } else if (
              typeof iwdOpcConfig !== 'undefined' &&
              typeof OnePage !== 'undefined' &&
              typeof PaymentMethod !== 'undefined'
            ) {
              $ji('.iwd_opc_loader_wrapper.active').hide();
            }

            if (window.awOSCForm) {
              form.enablePlaceOrderButton();
              form.hidePleaseWaitNotice();
              form.hideOverlay();
            }
          },
        };

        if (THIS.options.cca) {
          options.cca = THIS.options.cca;
        }

        THIS.tokenizeOptions = options;
        THIS.hps = new Heartland.HPS(options);

        if (document.getElementById('amscheckout-onepage')) {
          var ssbanner = document.getElementById('ss-banner');
          var ccnumber = document.getElementById('cc-number');
          var expirationdate = document.getElementById('expiration-dat');
          var ccv = document.getElementById('payment-buttons-container');

          if (ssbanner) {
            ssbanner.style.backgroundSize = '325px 40px';
          }
          if (ccnumber) {
            ccnumber.className = 'securesubmit_amasty_one_page_checkout';
          }
          if (expirationdate) {
            expirationdate.className = 'securesubmit_amasty_one_page_checkout';
          }
          if (ccv) {
            ccv.className = 'securesubmit_amasty_one_page_checkout';
          }
        }
      } else {
        Heartland.Card.attachNumberEvents(
          '#' + THIS.options.code + '_cc_number'
        );
        Heartland.Card.attachExpirationEvents(
          '#' + THIS.options.code + '_exp_date'
        );
        Heartland.Card.attachCvvEvents('#' + THIS.options.code + '_cvv_number');
      }
    },
    isOnePageCheckout: function() {
      return (
        typeof OPC !== 'undefined' ||
        (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') ||
        (typeof iwdOpcConfig !== 'undefined' &&
          typeof OnePage !== 'undefined' &&
          typeof PaymentMethod !== 'undefined') ||
        window.secureSubmitAmastyCompleteCheckoutOriginal ||
        window.oscPlaceOrderOriginal ||
        window.awOSCForm
      );
    },
    completeCheckout: function() {
      if (typeof OPC !== 'undefined') {
        checkout.setLoadWaiting(true);
        new Ajax.Request(checkout.saveUrl, {
          method: 'post',
          parameters: Form.serialize(checkout.form),
          onSuccess: checkout.setResponse.bind(checkout),
          onFailure: checkout.ajaxFailure.bind(checkout),
        });
      } else if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
        IWD.OPC.Checkout.xhr = $j_opc.post(
          IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',
          $j_opc('#co-payment-form').serializeArray(),
          IWD.OPC.preparePaymentResponse,
          'json'
        );
      } else if (
        typeof iwdOpcConfig !== 'undefined' &&
        typeof OnePage !== 'undefined' &&
        typeof PaymentMethod !== 'undefined'
      ) {
        $ji('.iwd_opc_loader_wrapper.active').show();
        Singleton.get(OnePage).saveOrder();
      } else if (window.secureSubmitAmastyCompleteCheckoutOriginal) {
        secureSubmitAmastyCompleteCheckoutOriginal();
      } else if (window.oscPlaceOrderOriginal) {
        $('onestepcheckout-place-order-loading').show();
        $('onestepcheckout-button-place-order').removeClassName(
          'onestepcheckout-btn-checkout'
        );
        $('onestepcheckout-button-place-order').addClassName(
          'place-order-loader'
        );
        oscPlaceOrderOriginal(THIS.__data.btn);
      } else if (typeof Payment !== 'undefined') {
        new Ajax.Request(payment.saveUrl, {
          method: 'post',
          parameters: Form.serialize(payment.form),
          onComplete: payment.onComplete,
          onSuccess: payment.onSave,
          onFailure: checkout.ajaxFailure.bind(checkout),
        });
      } else if (document.getElementById('multishipping-billing-form')) {
        document.getElementById('payment-continue').enable();
        document.getElementById('multishipping-billing-form').submit();
      } else if (window.awOSCForm) {
        awOSCForm._secureSubmitOldPlaceOrder();
      }
    },
    initializeCCA: function(callback) {
      if (!THIS.options.cca) {
        callback();
        return;
      }

      Cardinal.__secureSubmitInitFrame =
        Cardinal.__secureSubmitInitFrame || false;
      if (!Cardinal.__secureSubmitInitFrame) {
        Cardinal.setup('init', {
          jwt: THIS.options.cca.jwt,
        });
        Cardinal.on('payments.validated', function(data, jwt) {
          var makeField = function(name, value) {
            var el = document.createElement('input');
            el.value = value;
            el.type = 'hidden';
            el.name = 'payment[cca_data_' + name + ']';
            $('payment_form_' + THIS.options.code).appendChild(el);
          };
          makeField('action_code', data.ActionCode);
          makeField(
            'cavv',
            data.Payment &&
            data.Payment.ExtendedData &&
            data.Payment.ExtendedData.CAVV
              ? data.Payment.ExtendedData.CAVV
              : ''
          );
          makeField(
            'eci',
            data.Payment &&
            data.Payment.ExtendedData &&
            data.Payment.ExtendedData.ECIFlag
              ? data.Payment.ExtendedData.ECIFlag
              : ''
          );
          makeField(
            'xid',
            data.Payment &&
            data.Payment.ExtendedData &&
            data.Payment.ExtendedData.XID
              ? data.Payment.ExtendedData.XID
              : ''
          );
          makeField(
            'token',
            data.Token && data.Token.Token ? data.Token.Token : ''
          );
          if (callback) {
            callback();
          }
        });
        Cardinal.__secureSubmitInitFrame = true;
      }

      Cardinal.trigger('jwt.update', THIS.options.cca.jwt);

      var payload = {
        OrderDetails: {
          OrderNumber: THIS.options.cca.orderNumber + 'cca',
        },
      };

      if (THIS.options.useIframes) {
        payload.Token = {
          Token: $(THIS.options.code + '_cardinal_token').value,
          ExpirationMonth: $('hps_securesubmit_cc_exp_month').value.replace(
            /\D/g,
            ''
          ),
          ExpirationYear: $('hps_securesubmit_cc_exp_year').value.replace(
            /\D/g,
            ''
          ),
        };
      } else {
        payload.Consumer = {
          Account: {
            AccountNumber: $('hps_securesubmit_cc_number').value.replace(
              /\D/g,
              ''
            ),
            CardCode: $('hps_securesubmit_cvv_number').value.replace(/\D/g, ''),
            ExpirationMonth: $('hps_securesubmit_cc_exp_month').value.replace(
              /\D/g,
              ''
            ),
            ExpirationYear: $('hps_securesubmit_cc_exp_year').value.replace(
              /\D/g,
              ''
            ),
          },
        };
      }

      Cardinal.start('cca', payload);
    },
    useStoredCard: function() {
      var newRadio = $('hps_securesubmit_stored_card_select_new');
      return !newRadio.checked;
    },
  };
  window.SecureSubmitMagento = THIS;

  $(document).on(
    'change',
    '#aw-onestepcheckout-payment-method #checkout-payment-method-load input[type=radio]',
    function() {
      if (
        document.getElementById('p_method_hps_securesubmit').checked == true
      ) {
        ClearValue();
      }
      if (
        document.getElementById('hps_securesubmit_stored_card_select_1')
          .checked == true &&
        document.getElementById('p_method_hps_securesubmit').checked == true
      ) {
        ClearValue();
      }
      if (
        document.getElementById('hps_securesubmit_stored_card_select_new')
          .checked == true &&
        document.getElementById('p_method_hps_securesubmit').checked == true
      ) {
        ClearValue();
      }
    }
  );

  function ClearValue() {
    document.getElementById('hps_securesubmit_cc_number').value = '';
    document.getElementById('hps_securesubmit_exp_date').value = '';
    document.getElementById('hps_securesubmit_cvv_number').value = '';
  }
})(window, window.document);

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
        var radio = $$(
          '[name="hps_securesubmit_stored_card_select"]:checked'
        )[0];
        var storedcardId = radio.value;
        var storedcardType = $(radio.id + '_card_type').value;

        new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
          method: 'post',
          parameters: {storedcard_id: storedcardId},
          onSuccess: function(response) {
            var data = response.responseJSON;
            if (data && data.token) {
              $('hps_securesubmit_cc_exp_month').value = parseInt(
                data.token.cc_exp_month
              );
              $('hps_securesubmit_cc_exp_year').value = data.token.cc_exp_year;
            }
            this.secureSubmitResponseHandler.call(this, {
              card_type: storedcardType,
              token_value: data.token.token_value,
              token_type: null, // 'supt'?
              token_expire: new Date().toISOString(),
              card: {
                number: data.token.cc_last4,
              },
            });
          }.bind(this),
          onFailure: function() {
            alert('Unknown error. Please try again.');
          },
        });
      } else {
        // Use stored card not checked, get new token
        if (SecureSubmitMagento.options.useIframes) {
          SecureSubmitMagento.hps.Messages.post(
            {
              accumulateData: true,
              action: 'tokenize',
              data: SecureSubmitMagento.tokenizeOptions,
            },
            'cardNumber'
          );
        } else {
          var validator = new Validation(multiForm);
          if (validator.validate()) {
            if ($('hps_securesubmit_exp_date').value) {
              var date = $('hps_securesubmit_exp_date').value.split('/');
              $('hps_securesubmit_cc_exp_month').value = date[0].trim();
              $('hps_securesubmit_cc_exp_year').value = date[1].trim();
            }

            new Heartland.HPS({
              publicKey: this.secureSubmitPublicKey,
              cardNumber: $('hps_securesubmit_cc_number').value,
              cardCvv: $('hps_securesubmit_cvv_number').value,
              cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
              cardExpYear: $('hps_securesubmit_cc_exp_year').value,
              success: this.secureSubmitResponseHandler.bind(this),
              error: this.secureSubmitResponseHandler.bind(this),
            }).tokenize();
          }
        }
      }
    },
    secureSubmitUseStoredCard: function() {
      var newRadio = $('hps_securesubmit_stored_card_select_new');
      return !newRadio.checked;
    },
    secureSubmitResponseHandler: function(response) {
      var tokenField = $('hps_securesubmit_token'),
        typeField = $('hps_securesubmit_cc_type'),
        lastFourField = $('hps_securesubmit_cc_last_four');
      tokenField.value = typeField.value = lastFourField.value = null;

      if (
        $('hps_securesubmit_exp_date') &&
        $('hps_securesubmit_exp_date').value
      ) {
        var date = $('hps_securesubmit_exp_date').value.split('/');
        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
      }

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
        typeField.value = response.card_type;

        // Continue Magento checkout steps
        document.getElementById('payment-continue').enable();
        multiForm.submit();
      } else {
        alert('Unexpected error.');
      }
    },
  };
  return secureSubmit;
}
var secureSubmitAmastyCompleteCheckoutOriginal;

// AheadWorks OneStepCheckout
Event.observe(document, 'aw_osc:onestepcheckout_form_init_before', function(e) {
  var form = e.memo.form;
  var oldAwOsc = Object.clone(form);
  form._secureSubmitOldPlaceOrder = oldAwOsc.placeOrder;

  form.placeOrder = function() {
    var checkedPaymentMethod = $$(
      '[name="' + awOSCForm.paymentMethodName + '"]:checked'
    );
    if (
      checkedPaymentMethod.length !== 1 ||
      checkedPaymentMethod[0].value !== 'hps_securesubmit'
    ) {
      this._secureSubmitOldPlaceOrder();
      return;
    }

    // Use stored card checked, get existing token data
    if (window.SecureSubmitMagento.useStoredCard()) {
      var radio = $$('[name="hps_securesubmit_stored_card_select"]:checked')[0];
      var storedcardId = radio.value;
      var storedcardType = $(radio.id + '_card_type').value;
      new Ajax.Request(form.secureSubmitGetTokenDataUrl, {
        method: 'post',
        parameters: {storedcard_id: storedcardId},
        onSuccess: function(response) {
          var data = response.responseJSON;
          if (data && data.token) {
            $('hps_securesubmit_cc_exp_month').value = parseInt(
              data.token.cc_exp_month
            );
            $('hps_securesubmit_cc_exp_year').value = data.token.cc_exp_year;
          }
          this.secureSubmitResponseHandler.call(this, {
            card_type: storedcardType,
            token_value: data.token.token_value,
            token_type: null, // 'supt'?
            token_expire: new Date().toISOString(),
            card: {
              number: data.token.cc_last4,
            },
          });
        }.bind(form),
        onFailure: function() {
          alert('Unknown error. Please try again.');
          form.enablePlaceOrderButton();
          form.hidePleaseWaitNotice();
          form.hideOverlay();
        },
      });
    } else {
      // Use stored card not checked, get new token
      if (window.SecureSubmitMagento.options.useIframes) {
        window.SecureSubmitMagento.hps.Messages.post(
          {
            accumulateData: true,
            action: 'tokenize',
            data: window.SecureSubmitMagento.tokenizeOptions,
          },
          'cardNumber'
        );
      } else {
        if (
          $('hps_securesubmit_exp_date') &&
          $('hps_securesubmit_exp_date').value
        ) {
          var date = $('hps_securesubmit_exp_date').value.split('/');
          $('hps_securesubmit_cc_exp_month').value = date[0].trim();
          $('hps_securesubmit_cc_exp_year').value = date[1].trim();
        }

        new Heartland.HPS({
          publicKey: form.secureSubmitPublicKey,
          cardNumber: $('hps_securesubmit_cc_number').value,
          cardCvv: $('hps_securesubmit_cvv_number').value,
          cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
          cardExpYear: $('hps_securesubmit_cc_exp_year').value,
          success: form.secureSubmitResponseHandler.bind(form),
          error: form.secureSubmitResponseHandler.bind(form),
        }).tokenize();
      }
    }
  };

  form.secureSubmitResponseHandler = function(response) {
    var tokenField = $('hps_securesubmit_token'),
      typeField = $('hps_securesubmit_cc_type'),
      lastFourField = $('hps_securesubmit_cc_last_four');
    tokenField.value = typeField.value = lastFourField.value = null;

    if (
      $('hps_securesubmit_exp_date') &&
      $('hps_securesubmit_exp_date').value
    ) {
      var date = $('hps_securesubmit_exp_date').value.split('/');
      $('hps_securesubmit_cc_exp_month').value = date[0].trim();
      $('hps_securesubmit_cc_exp_year').value = date[1].trim();
    }

    if (window.SecureSubmitMagento.skipCreditCard) {
      window.SecureSubmitMagento.completeCheckout();
      return;
    }

    if (response && response.error) {
      if (response.error.message) {
        alert(response.error.message);
      }
      this.enablePlaceOrderButton();
      this.hidePleaseWaitNotice();
      this.hideOverlay();
    } else if (response && response.token_value) {
      tokenField.value = response.token_value;
      lastFourField.value = response.card.number.substr(-4);
      typeField.value = response.card_type;

      window.SecureSubmitMagento.initializeCCA(
        function() {
          // Continue Magento checkout steps
          form._secureSubmitOldPlaceOrder();
        }.bind(this)
      );
    } else {
      alert('Unexpected error.');
    }
  };
});

document.observe('dom:loaded', function() {
  // Override default Payment save handler
  if (typeof Payment !== 'undefined') {
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
          var radio = $$(
            '[name="hps_securesubmit_stored_card_select"]:checked'
          )[0];
          var storedcardId = radio.value;
          var storedcardType = $(radio.id + '_card_type').value;
          checkout.setLoadWaiting('payment');
          new Ajax.Request(this.secureSubmitGetTokenDataUrl, {
            method: 'post',
            parameters: {storedcard_id: storedcardId},
            onSuccess: function(response) {
              var data = response.responseJSON;
              if (data && data.token) {
                $('hps_securesubmit_cc_exp_month').value = parseInt(
                  data.token.cc_exp_month
                );
                $('hps_securesubmit_cc_exp_year').value =
                  data.token.cc_exp_year;
              }
              this.secureSubmitResponseHandler.call(this, {
                card_type: storedcardType,
                token_value: data.token.token_value,
                token_type: null, // 'supt'?
                token_expire: new Date().toISOString(),
                card: {
                  number: data.token.cc_last4,
                },
              });
            }.bind(this),
            onFailure: function() {
              alert('Unknown error. Please try again.');
              checkout.setLoadWaiting(false);
            },
          });
        } else {
          // Use stored card not checked, get new token
          if (SecureSubmitMagento.options.useIframes) {
            checkout.setLoadWaiting('payment');
            SecureSubmitMagento.hps.Messages.post(
              {
                accumulateData: true,
                action: 'tokenize',
                data: SecureSubmitMagento.tokenizeOptions,
              },
              'cardNumber'
            );
          } else {
            var validator = new Validation(this.form);
            if (this.validate() && validator.validate()) {
              checkout.setLoadWaiting('payment');

              if (
                $('hps_securesubmit_exp_date') &&
                $('hps_securesubmit_exp_date').value
              ) {
                var date = $('hps_securesubmit_exp_date').value.split('/');
                $('hps_securesubmit_cc_exp_month').value = date[0].trim();
                $('hps_securesubmit_cc_exp_year').value = date[1].trim();
              }

              new Heartland.HPS({
                publicKey: this.secureSubmitPublicKey,
                cardNumber: $('hps_securesubmit_cc_number').value,
                cardCvv: $('hps_securesubmit_cvv_number').value,
                cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
                cardExpYear: $('hps_securesubmit_cc_exp_year').value,
                success: this.secureSubmitResponseHandler.bind(this),
                error: this.secureSubmitResponseHandler.bind(this),
              }).tokenize();
            }
          }
        }
      },
      secureSubmitUseStoredCard: function() {
        var newRadio = $('hps_securesubmit_stored_card_select_new');
        return !newRadio.checked;
      },
      secureSubmitResponseHandler: function(response) {
        var tokenField = $('hps_securesubmit_token'),
          typeField = $('hps_securesubmit_cc_type'),
          lastFourField = $('hps_securesubmit_cc_last_four');
        tokenField.value = typeField.value = lastFourField.value = null;

        if (
          $('hps_securesubmit_exp_date') &&
          $('hps_securesubmit_exp_date').value
        ) {
          var date = $('hps_securesubmit_exp_date').value.split('/');
          $('hps_securesubmit_cc_exp_month').value = date[0].trim();
          $('hps_securesubmit_cc_exp_year').value = date[1].trim();
        }

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
          typeField.value = response.card_type;

          SecureSubmitMagento.initializeCCA(
            function() {
              // Continue Magento checkout steps
              new Ajax.Request(this.saveUrl, {
                method: 'post',
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout),
                parameters: Form.serialize(this.form),
              });
            }.bind(this)
          );
        } else {
          alert('Unexpected error.');
        }
      },
    });
  }

  if (typeof OPC !== 'undefined') {
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
          SecureSubmitMagento.hps.Messages.post(
            {
              accumulateData: true,
              action: 'tokenize',
              data: SecureSubmitMagento.tokenizeOptions,
            },
            'cardNumber'
          );
        } else {
          if (
            $('hps_securesubmit_exp_date') &&
            $('hps_securesubmit_exp_date').value
          ) {
            var date = $('hps_securesubmit_exp_date').value.split('/');
            $('hps_securesubmit_cc_exp_month').value = date[0].trim();
            $('hps_securesubmit_cc_exp_year').value = date[1].trim();
          }

          new Heartland.HPS({
            publicKey: this.secureSubmitPublicKey,
            cardNumber: $('hps_securesubmit_cc_number').value,
            cardCvv: $('hps_securesubmit_cvv_number').value,
            cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
            cardExpYear: $('hps_securesubmit_cc_exp_year').value,
            success: this.secureSubmitResponseHandler.bind(this),
            error: this.secureSubmitResponseHandler.bind(this),
          }).tokenize();
        }
      },
      secureSubmitResponseHandler: function(response) {
        var tokenField = $('hps_securesubmit_token'),
          typeField = $('hps_securesubmit_cc_type'),
          lastFourField = $('hps_securesubmit_cc_last_four');
        tokenField.value = typeField.value = lastFourField.value = null;

        if (
          $('hps_securesubmit_exp_date') &&
          $('hps_securesubmit_exp_date').value
        ) {
          var date = $('hps_securesubmit_exp_date').value.split('/');
          $('hps_securesubmit_cc_exp_month').value = date[0].trim();
          $('hps_securesubmit_cc_exp_year').value = date[1].trim();
        }

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
          typeField.value = response.card_type;
          lastFourField.value = response.card.number.substr(-4);
          typeField.value = response.card_type;

          this.setLoadWaiting(true);
          var params = Form.serialize(this.form);
          var request = new Ajax.Request(this.saveUrl, {
            method: 'post',
            parameters: params,
            onSuccess: this.setResponse.bind(this),
            onFailure: this.ajaxFailure.bind(this),
          });
        } else {
          alert('Unexpected error.');
        }
      },
    });
  }

  var cloneFunction = function(that) {
    var temp = function temporary() {
      return that.apply(this, arguments);
    };
    for (var key in this) {
      if (this.hasOwnProperty(key)) {
        temp[key] = this[key];
      }
    }
    return temp;
  };
  // Amasty completeCheckout();

  if (
    typeof completeCheckout === 'function' &&
    document.getElementById('amscheckout-onepage')
  ) {
    secureSubmitAmastyCompleteCheckoutOriginal = cloneFunction(
      completeCheckout
    );

    try {
      var ele;
      ele = document.createElement('div');
      ele.id = 'co-payment-form-update';
      var pEle = document.querySelector(
        '#amscheckout-main > div.amscheckout > div > div.second-column > div:nth-child(3) > div.payment-method'
      );
      pEle.insertBefore(ele, pEle.childNodes[2]);
    } catch (e) {}
    var container = document.getElementById('payment-buttons-container');
    if (container && container.parentNode) {
      // container.parentNode should always exist, but we're playing it safe above
      container.parentNode.removeChild(container);
    }

    completeCheckout = function(btn) {
      var validator = new Validation('amscheckout-onepage');
      var form = $('amscheckout-onepage');

      if (validator.validate()) {
        var currentPayment = payment.currentMethod;
        if (currentPayment != 'hps_securesubmit') {
          secureSubmitAmastyCompleteCheckoutOriginal(btn);
          return;
        }

        if (
          $('hps_securesubmit_exp_date') &&
          $('hps_securesubmit_exp_date').value
        ) {
          var date = $('hps_securesubmit_exp_date').value.split('/');
          $('hps_securesubmit_cc_exp_month').value = date[0].trim();
          $('hps_securesubmit_cc_exp_year').value = date[1].trim();
        }

        if (secureSubmitUseStoredCardAOSC()) {
          var radio = $$(
            '[name="hps_securesubmit_stored_card_select"]:checked'
          )[0];
          var storedcardId = radio.value;
          var storedcardType = $(radio.id + '_card_type').value;
          new Ajax.Request(window.payment.secureSubmitGetTokenDataUrlOSC, {
            method: 'post',
            parameters: {storedcard_id: storedcardId},
            onSuccess: function(response) {
              var data = response.responseJSON;
              secureSubmitResponseHandlerAOSC(
                {
                  card_type: storedcardType,
                  token_value: data.token.token_value,
                  token_type: null, // 'supt'?
                  token_expire: new Date().toISOString(),
                  card: {
                    number: data.token.cc_last4,
                  },
                },
                btn
              );
            },
            onFailure: function() {
              alert('Unknown error. Please try again.');
            },
          });
        } else {
          if (SecureSubmitMagento.options.useIframes) {
            SecureSubmitMagento.hps.Messages.post(
              {
                accumulateData: true,
                action: 'tokenize',
                data: SecureSubmitMagento.tokenizeOptions,
              },
              'cardNumber'
            );
          } else {
            new Heartland.HPS({
              publicKey: window.payment.secureSubmitPublicKeyOSC,
              cardNumber: $('hps_securesubmit_cc_number').value,
              cardCvv: $('hps_securesubmit_cvv_number').value,
              cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
              cardExpYear: $('hps_securesubmit_cc_exp_year').value,
              success: function(response) {
                secureSubmitResponseHandlerAOSC(response, btn);
              },
              error: function(response) {
                secureSubmitResponseHandlerAOSC(response, btn);
              },
            }).tokenize();
          }
        }
      }
    };

    secureSubmitUseStoredCardAOSC = function() {
      var newRadio = $('hps_securesubmit_stored_card_select_new');
      return !newRadio.checked;
    };

    secureSubmitResponseHandlerAOSC = function(response, btn) {
      var tokenField = $('hps_securesubmit_token'),
        typeField = $('hps_securesubmit_cc_type'),
        lastFourField = $('hps_securesubmit_cc_last_four');
      tokenField.value = typeField.value = lastFourField.value = null;

      if (
        $('hps_securesubmit_exp_date') &&
        $('hps_securesubmit_exp_date').value
      ) {
        var date = $('hps_securesubmit_exp_date').value.split('/');
        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
      }

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
        typeField.value = response.card_type;

        secureSubmitAmastyCompleteCheckoutOriginal(btn);
      } else {
        alert('Unexpected error.');
      }
    };
  }

  // MageStore One Step Checkout
  if (typeof oscPlaceOrder === 'function') {
    window.oscPlaceOrderOriginal = cloneFunction(oscPlaceOrder);
    oscPlaceOrder = function(btn) {
      var validator = new Validation('one-step-checkout-form');
      var form = $('one-step-checkout-form');
      SecureSubmitMagento.__data.btn = btn;
      if (validator.validate()) {
        var currentPayment = $RF(form, 'payment[method]');
        if (currentPayment != 'hps_securesubmit') {
          oscPlaceOrderOriginal(btn);
          return;
        }
        $('onestepcheckout-place-order-loading').hide();
        $('onestepcheckout-button-place-order').removeClassName(
          'place-order-loader'
        );
        $('onestepcheckout-button-place-order').addClassName(
          'onestepcheckout-btn-checkout'
        );
        if (secureSubmitUseStoredCardOSC()) {
          var radio = $$(
            '[name="hps_securesubmit_stored_card_select"]:checked'
          )[0];
          var storedcardId = radio.value;
          var storedcardType = $(radio.id + '_card_type').value;
          new Ajax.Request(window.payment.secureSubmitGetTokenDataUrlOSC, {
            method: 'post',
            parameters: {storedcard_id: storedcardId},
            onSuccess: function(response) {
              var data = response.responseJSON;
              if (data && data.token) {
                $('hps_securesubmit_cc_exp_month').value = parseInt(
                  data.token.cc_exp_month
                );
                $('hps_securesubmit_cc_exp_year').value =
                  data.token.cc_exp_year;
              }
              secureSubmitResponseHandlerOSC(
                {
                  card_type: storedcardType,
                  token_value: data.token.token_value,
                  token_type: null, // 'supt'?
                  token_expire: new Date().toISOString(),
                  card: {
                    number: data.token.cc_last4,
                  },
                },
                btn
              );
            },
            onFailure: function() {
              alert('Unknown error. Please try again.');
              $('onestepcheckout-place-order-loading').show();
              $('onestepcheckout-button-place-order').removeClassName(
                'onestepcheckout-btn-checkout'
              );
              $('onestepcheckout-button-place-order').addClassName(
                'place-order-loader'
              );
            },
          });
        } else {
          if (SecureSubmitMagento.options.useIframes) {
            SecureSubmitMagento.hps.Messages.post(
              {
                accumulateData: true,
                action: 'tokenize',
                data: SecureSubmitMagento.tokenizeOptions,
              },
              'cardNumber'
            );
          } else {
            if (
              $('hps_securesubmit_exp_date') &&
              $('hps_securesubmit_exp_date').value
            ) {
              var date = $('hps_securesubmit_exp_date').value.split('/');
              $('hps_securesubmit_cc_exp_month').value = date[0].trim();
              $('hps_securesubmit_cc_exp_year').value = date[1].trim();
            }

            new Heartland.HPS({
              publicKey: window.payment.secureSubmitPublicKeyOSC,
              cardNumber: $('hps_securesubmit_cc_number').value,
              cardCvv: $('hps_securesubmit_cvv_number').value,
              cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
              cardExpYear: $('hps_securesubmit_cc_exp_year').value,
              success: function(response) {
                secureSubmitResponseHandlerOSC(response, btn);
              },
              error: function(response) {
                secureSubmitResponseHandlerOSC(response, btn);
              },
            }).tokenize();
          }
        }
      }
    };

    secureSubmitUseStoredCardOSC = function() {
      var newRadio = $('hps_securesubmit_stored_card_select_new');
        return !newRadio.checked;
    };

    secureSubmitResponseHandlerOSC = function(response, btn) {
      var tokenField = $('hps_securesubmit_token'),
        typeField = $('hps_securesubmit_cc_type'),
        lastFourField = $('hps_securesubmit_cc_last_four');
      tokenField.value = typeField.value = lastFourField.value = null;

      if (
        $('hps_securesubmit_exp_date') &&
        $('hps_securesubmit_exp_date').value
      ) {
        var date = $('hps_securesubmit_exp_date').value.split('/');
        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
      }

      if (SecureSubmitMagento.skipCreditCard) {
        SecureSubmitMagento.completeCheckout();
        return;
      }

      if (response && response.error) {
        if (response.error.message) {
          alert(response.error.message);
        }

        $('onestepcheckout-place-order-loading').hide();
        $('onestepcheckout-button-place-order').removeClassName(
          'place-order-loader'
        );
        $('onestepcheckout-button-place-order').addClassName(
          'onestepcheckout-btn-checkout'
        );
      } else if (response && response.token_value) {
        tokenField.value = response.token_value;
        lastFourField.value = response.card.number.substr(-4);
        typeField.value = response.card_type;

        $('onestepcheckout-place-order-loading').show();
        $('onestepcheckout-button-place-order').removeClassName(
          'onestepcheckout-btn-checkout'
        );
        $('onestepcheckout-button-place-order').addClassName(
          'place-order-loader'
        );
        window.SecureSubmitMagento.initializeCCA(
          function() {
            // Continue Magento checkout steps
            oscPlaceOrderOriginal(btn);
          }.bind(this)
        );
      } else {
        alert('Unexpected error.');
        $('onestepcheckout-place-order-loading').show();
        $('onestepcheckout-button-place-order').removeClassName(
          'onestepcheckout-btn-checkout'
        );
        $('onestepcheckout-button-place-order').addClassName(
          'place-order-loader'
        );
      }
    };
  }

  // IWD OPC
  if (typeof IWD !== 'undefined' && typeof IWD.OPC !== 'undefined') {
    if (typeof IWD.OPC._secureSubmitOldSavePayment === 'undefined') {
      var oldIWDOPC = Object.clone(IWD.OPC);
      IWD.OPC._secureSubmitOldSavePayment = oldIWDOPC.savePayment;
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
          SecureSubmitMagento.hps.Messages.post(
            {
              accumulateData: true,
              action: 'tokenize',
              data: SecureSubmitMagento.tokenizeOptions,
            },
            'cardNumber'
          );
        } else {
          if (
            $('hps_securesubmit_exp_date') &&
            $('hps_securesubmit_exp_date').value
          ) {
            var date = $('hps_securesubmit_exp_date').value.split('/');
            $('hps_securesubmit_cc_exp_month').value = date[0].trim();
            $('hps_securesubmit_cc_exp_year').value = date[1].trim();
          }

          new Heartland.HPS({
            publicKey: this.secureSubmitPublicKey,
            cardNumber: $('hps_securesubmit_cc_number').value,
            cardCvv: $('hps_securesubmit_cvv_number').value,
            cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
            cardExpYear: $('hps_securesubmit_cc_exp_year').value,
            success: this.secureSubmitResponseHandler.bind(this),
            error: this.secureSubmitResponseHandler.bind(this),
          }).tokenize();
        }
      },
      secureSubmitResponseHandler: function(response) {
        var tokenField = $('hps_securesubmit_token'),
          typeField = $('hps_securesubmit_cc_type'),
          lastFourField = $('hps_securesubmit_cc_last_four');
        tokenField.value = typeField.value = lastFourField.value = null;

        if (
          $('hps_securesubmit_exp_date') &&
          $('hps_securesubmit_exp_date').value
        ) {
          var date = $('hps_securesubmit_exp_date').value.split('/');
          $('hps_securesubmit_cc_exp_month').value = date[0].trim();
          $('hps_securesubmit_cc_exp_year').value = date[1].trim();
        }

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
          typeField.value = response.card_type;

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
      },
    });
  }

  // Latest Version of IWD One page Checkou
  if (
    typeof iwdOpcConfig !== 'undefined' &&
    typeof OnePage !== 'undefined' &&
    typeof PaymentMethod !== 'undefined'
  ) {
    PaymentMethod.prototype.initPaymentMethods = function() {
      Singleton.get(PaymentMethodIWD).init();
    };

    PaymentMethod.prototype.saveSection = function() {
      var _this = this;
      var _thisArguments = arguments;
      _this.showLoader(Singleton.get(OnePage).sectionContainer);
      switch (_this.getPaymentMethodCode()) {
        case Singleton.get(PaymentMethodIWD).code:
          Singleton.get(PaymentMethodIWD).originalThis = _this;
          Singleton.get(PaymentMethodIWD).originalArguments = _thisArguments;
          Singleton.get(PaymentMethodIWD).savePayment();
          break;
        default:
          OnePage.prototype.saveSection.apply(_this, _thisArguments);
      }
    };

    function PaymentMethodIWD() {
      PaymentMethod.apply(this);
      this.name = 'payment_method_hps_securesubmit';
      this.paymentForm = null;
      this.code = 'hps_securesubmit';
      this.originalThis = null;
      this.originalArguments = null;
      this.saveOrderInProgress = false;
    }

    PaymentMethodIWD.prototype = Object.create(PaymentMethod.prototype);
    PaymentMethodIWD.prototype.constructor = PaymentMethodIWD;

    PaymentMethodIWD.prototype.init = function() {
      // Displaying Card datas
      var code = $ji('#iwd_opc_payment_method_select').val();
      if (code == 'hps_securesubmit') {
        $ji(
          '.iwd_opc_payment_method_forms .iwd_opc_payment_method_form ul#payment_form_hps_securesubmit'
        ).show();
      }
      this.initChangeCard();
      this.saveUrl = this.config.savePaymentUrl;
    };

    PaymentMethodIWD.prototype.initChangeCard = function() {
      var _this = this;
      $ji(document).on(
        'change',
        _this.sectionContainer + ' #iwd_opc_payment_method_select',
        function() {
          var code = $ji(this).val();
          if (code == 'hps_securesubmit') {
            $ji(
              _this.sectionContainer +
                ' .iwd_opc_payment_method_forms .iwd_opc_payment_method_form ul#payment_form_hps_securesubmit'
            ).show();
            setTimeout(function() {
              $ji('ul#payment_form_hps_securesubmit .validation-advice').hide();
            }, 100);
          }
        }
      );
    };

    PaymentMethodIWD.prototype.getSaveData = function() {
      var data = Singleton.get(OnePage).getSaveData();
      data.push({
        name: 'controller',
        value: 'onepage',
      });
      return data;
    };

    PaymentMethodIWD.prototype.savePayment = function() {
      // Add the `required-entry` class back to the fields to ensure they are present
      if ($ji('#payment_form_' + this.code + ' .required-entry').length === 0) {
        $ji('#payment_form_' + this.code + ' .input-text').addClass(
          'required-entry'
        );
      }

      // Use stored card checked, get existing token data
      if (this.secureSubmitUseStoredCard()) {
        var radio = $$(
          '[name="hps_securesubmit_stored_card_select"]:checked'
        )[0];
        var storedcardId = radio.value;
        var storedcardType = $(radio.id + '_card_type').value;
        new Ajax.Request(PaymentMethod.prototype.secureSubmitGetTokenDataUrl, {
          method: 'post',
          parameters: {storedcard_id: storedcardId},
          onSuccess: function(response) {
            var data = response.responseJSON;
            if (data && data.token) {
              $('hps_securesubmit_cc_exp_month').value = parseInt(
                data.token.cc_exp_month
              );
              $('hps_securesubmit_cc_exp_year').value = data.token.cc_exp_year;
            }
            this.secureSubmitResponseHandler.call(this, {
              card_type: storedcardType,
              token_value: data.token.token_value,
              token_type: null, // 'supt'?
              token_expire: new Date().toISOString(),
              card: {
                number: data.token.cc_last4,
              },
            });
          }.bind(this),
          onFailure: function() {
            alert('Unknown error. Please try again.');
          },
        });
      } else {
        // Use stored card not checked, get new token
        if (SecureSubmitMagento.options.useIframes) {
          SecureSubmitMagento.hps.Messages.post(
            {
              accumulateData: true,
              action: 'tokenize',
              data: SecureSubmitMagento.tokenizeOptions,
            },
            'cardNumber'
          );
        } else {
          var validator = new Validation('hps_securesubmit_cc_form');
          if (validator.validate()) {
            if (
              $('hps_securesubmit_exp_date') &&
              $('hps_securesubmit_exp_date').value
            ) {
              var date = $('hps_securesubmit_exp_date').value.split('/');
              $('hps_securesubmit_cc_exp_month').value = date[0].trim();
              $('hps_securesubmit_cc_exp_year').value = date[1].trim();
            }

            new Heartland.HPS({
              publicKey: PaymentMethod.prototype.secureSubmitPublicKey,
              cardNumber: $('hps_securesubmit_cc_number').value,
              cardCvv: $('hps_securesubmit_cvv_number').value,
              cardExpMonth: $('hps_securesubmit_cc_exp_month').value,
              cardExpYear: $('hps_securesubmit_cc_exp_year').value,
              success: this.secureSubmitResponseHandler.bind(this),
              error: this.secureSubmitResponseHandler.bind(this),
            }).tokenize();
          } else {
            $ji('.iwd_opc_loader_wrapper.active').hide();
          }
        }
      }
    };

    PaymentMethodIWD.prototype.secureSubmitUseStoredCard = function() {
      var newRadio = $('hps_securesubmit_stored_card_select_new');
      return !newRadio.checked;
    };

    PaymentMethodIWD.prototype.secureSubmitResponseHandler = function(
      response
    ) {
      var tokenField = $('hps_securesubmit_token'),
        typeField = $('hps_securesubmit_cc_type'),
        lastFourField = $('hps_securesubmit_cc_last_four');
      tokenField.value = typeField.value = lastFourField.value = null;

      if (
        $('hps_securesubmit_exp_date') &&
        $('hps_securesubmit_exp_date').value
      ) {
        var date = $('hps_securesubmit_exp_date').value.split('/');
        $('hps_securesubmit_cc_exp_month').value = date[0].trim();
        $('hps_securesubmit_cc_exp_year').value = date[1].trim();
      }

      if (SecureSubmitMagento.skipCreditCard) {
        SecureSubmitMagento.completeCheckout();
        return;
      }

      if (response && response.error) {
        if (response.error.message) {
          alert(response.error.message);
          $ji('.iwd_opc_loader_wrapper.active').hide();
        }
      } else if (response && response.token_value) {
        tokenField.value = response.token_value;
        lastFourField.value = response.card.number.substr(-4);
        typeField.value = response.card_type;

        var data = this.getSaveData();
        $ji('.iwd_opc_loader_wrapper.active').show();
        this.ajaxCall(this.saveUrl, data, this.onSaveOrderSuccess);
      } else {
        alert('Unexpected error.');
      }
    };
  }
    // FireCheckout
    if (typeof FireCheckout !== 'undefined') {
        Object.extend(FireCheckout.prototype, {
            save: function (urlSuffix, forceSave) {
                if (this.loadWaiting != false) {
                    return;
                }

                if (!this.validate()) {
                    return;
                }

                if (payment.currentMethod) {
                    // HPS heartland
                    if (!forceSave && payment.currentMethod.indexOf("hps_securesubmit") === 0) {
                        payment.save();
                        return;
                    }
                    // HPS heartland
                }

                checkout.setLoadWaiting(true);
                var params = Form.serialize(this.form, true);
                $('review-please-wait').show();

                urlSuffix = urlSuffix || '';
                var request = new Ajax.Request(this.urls.save + urlSuffix, {
                    method: 'post',
                    parameters: params,
                    onSuccess: this.setResponse.bind(this),
                    onFailure: this.ajaxFailure.bind(this)
                });
            },
        });
    }
    // FireCheckout
});
