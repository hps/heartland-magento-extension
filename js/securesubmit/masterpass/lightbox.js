(function (window, document) {
    var lookupUrl = '/securesubmit/masterpass/start';

    // Checkout success handler
    function startMasterPassCheckout(payload) {
      var data = {
        requestToken: payload.processorTransactionId,
        callbackUrl: payload.returnUrl,
        merchantCheckoutId: payload.merchantCheckoutId,
        allowedCardTypes: ['master','amex','diners','discover','visa'],
        version: 'v6'
      };
      var cardIds = $$('[name="masterpass_card_id"]:checked');

      if (cardIds.length === 1) {
        data.cardId = cardIds[0].value;
      }
      if (payload.preCheckoutTransactionId) {
        data.precheckoutTransactionId = payload.preCheckoutTransactionId;
      }
      if (payload.walletName) {
        data.walletName = payload.walletName;
      }
      if (payload.walletId) {
        data.consumerwalletId = payload.walletId;
      }

      MasterPass.client.checkout(data);
    }

    // Connect success handler
    function startMasterPassConnect(payload) {
      MasterPass.client.connect({
        pairingRequestToken: payload.processorTransactionIdPairing,
        callbackUrl: payload.returnUrl,
        merchantCheckoutId: payload.merchantCheckoutId,
        requestedDataTypes: '[CARD]',
        requestPairing: true,
        version: 'v6'
      });
    }

    function clickHandler(data, callback) {
      var checkout = checkout || {};
      checkout.ajaxFailure = checkout.ajaxFailure || function () { };
      return function (e) {
        e.preventDefault();
        if (payment.currentMethod.indexOf('hps_masterpass') === 0) {
          var request = new Ajax.Request(lookupUrl, {
            method:     'post',
            onComplete: function () {},
            onSuccess:  function (response) {
              var resp = JSON.parse(response.responseText);
              if (resp.result === 'error' && resp.redirect) {
                window.location.href = resp.redirect;
              }
              if (resp.result === 'error') {
                checkout.ajaxFailure.bind(checkout);
              }
              callback(resp.data);
            },
            onFailure:  checkout.ajaxFailure.bind(checkout),
            parameters: data
          });
        }
      };
    }

    function setSubmitHandler() {
      var button = $$('#payment-buttons-container button');
      var data = {};
      if (button.length !== 1) {
        return;
      }
      button = button[0];
      if ($('hps_masterpass_connected')) {
        data.pair = true;
      }
      button.observe('click', clickHandler(data, startMasterPassCheckout));
    }

    function setConnectHandler() {
      var button = $('securesubmit-connect-with-masterpass');
      if (!button) {
        return;
      }
      button.observe('click', clickHandler({pair: true}, startMasterPassConnect));
    }

    function setPaymentCallbacks() {
      if (typeof Payment === 'undefined') {
        return;
      }

      Payment.prototype.save = Payment.prototype.save.wrap(function (save) {
        var validator = new Validation(this.form);
        if (this.validate() && validator.validate()) {

          if (payment.currentMethod.indexOf('hps_masterpass') === 0) {
            var request = new Ajax.Request(this.saveUrl, {
              method:     'post',
              onComplete: function () {},
              onSuccess:  function () {},
              onFailure:  checkout.ajaxFailure.bind(checkout),
              parameters: Form.serialize(this.form)
            });
          } else {
            save(); //return default method
          }
        }
      });
    }

    document.observe('dom:loaded', function () {
      setPaymentCallbacks();
      setSubmitHandler();
      setConnectHandler();
    });
}(window, document));
