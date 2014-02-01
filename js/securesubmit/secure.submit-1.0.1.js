/*global $ */
var hps = (function () {
    "use strict";

    var HPS = {

        Tag: "SecureSubmit",

        Urls: {
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

			new Ajax.JSONP(gateway_url, {
				parameters: params,
				onComplete: function(json) {

					// Request failed, handle error
					if (typeof json.error === 'object') {
						// call error handler if provided and valid
						if (typeof options.error === 'function') {
							options.error(json.error);
						} else {
							// handle exception
							HPS.error(json.error.message);
						}
					} else if (typeof options.success === 'function') {
						options.success(json);
					}
				}
			});

        },
		
		trim: function (string) {	
			
			if (string !== undefined && typeof string === "string" ) {
				
				string = string.toString().replace(/^\s\s*/, '').replace(/\s\s*$/, '');
			}
			
			return string;						
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
}());

Ajax.JSONP = Class.create(Ajax.Base, (function() {
	var id = 0,
		head = document.getElementsByTagName('head')[0];

	return {
		initialize: function($super, url, options) {
			$super(options);
			this.request(url);
		},

		request: function(url) {
			var callbackName = '_prototypeJSONPCallback_' + (id++),
				self = this,
				script;

			this.options.parameters["callback"] = callbackName;

			url += (url.include('?') ? '&' : '?') + Object.toQueryString(this.options.parameters);

			window[callbackName] = function(json) {
				script.remove();
				script = null;
				window[callbackName] = undefined;
				if (self.options.onComplete) {
					self.options.onComplete.call(self, json);
				}
			}
			script = new Element('script', {
				type: 'text/javascript',
				src: url
			});
			head.appendChild(script);
		}
	};
})());
