/*global $ */
var hps = (function ($) {
    "use strict";

    var HPS = {

        Tag: "SecureSubmit",

        Urls: {
            UAT: "https://posgateway.uat.secureexchange.net/Hps.Exchange.PosGateway.Hpf.v1/api/token",
            CERT: "https://posgateway.cert.secureexchange.net/Hps.Exchange.PosGateway.Hpf.v1/api/token",
            PROD: "https://api.heartlandportico.com/SecureSubmit.v1/api/token"
        },

        tokenize: function (options) {
            var gateway_url, params, env, getter_impl;

            // add additional service parameters
            params = $H({
                "api_key": options.data.public_key,
                "object": "token",
                "token_type": "supt",
                "_method": "post",
                "card[number]": HPS.trim(options.data.number),
                "card[cvc]": HPS.trim(options.data.cvc),
                "card[exp_month]": HPS.trim(options.data.exp_month),
                "card[exp_year]": HPS.trim(options.data.exp_year)
            });

            env = options.data.public_key.split("_")[1];

            if (env === "uat") {
                gateway_url = HPS.Urls.UAT;
            } else if (env === "cert") {
                gateway_url = HPS.Urls.CERT;
            } else {
                gateway_url = HPS.Urls.PROD;
            }

            // request token

            getter_impl = Ajax.Response.prototype._getHeaderJSON;

            new Ajax.Request(gateway_url, {
                method: "get",
                parameters: params,
                onCreate: function(request){
                    Ajax.Response.prototype._getHeaderJSON = Prototype.emptyFunction;
                },
                onComplete: function(response) {

                    Ajax.Response.prototype._getHeaderJSON = getter_impl;

                    var json = response.responseText.evalJSON();

                    // Request failed, handle error
                    if (response.status !== 200) {
                        // call error handler if provided and valid
                        if (typeof options.error === 'function') {
                            options.error(json);
                        }
                        else {
                            // handle exception
                            HPS.error(json.message);
                        }
                    }
                    else if(typeof options.success === 'function') {
                        options.success(json);
                    }
                }
            });

        },

        empty: function (val) {
            return val === undefined || val.length === 0;
        },

        error: function (message) {
            if (console && console.log) {
                console.log([HPS.Tag, ": ", message].join(""));
            }
        },

        trim: function (string) {
            return string.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
        }
    };

    return HPS;
}($));
