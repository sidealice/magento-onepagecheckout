;
var IWD=IWD||{};
$j = jQuery;
IWD.LIPP = {
	config: null,
	
	init: function(){
		if (typeof(lippConfig)!="undefined"){
			this.config = $j.parseJSON(lippConfig);
			if (this.config.paypalLightBoxEnabled==true){
				this.initOPC();
				this.initOnCart();
			}
		}
	}, 
	
	initOPC: function(){
		$j(document).on('click', '.opc-wrapper-opc #checkout-payment-method-load .radio', function(){
			var method = payment.currentMethod;
			if (method.indexOf('paypaluk_express')!=-1 || method.indexOf('paypal_express')!=-1){
				PAYPAL.apps.Checkout.startFlow(IWD.LIPP.config.baseUrl + 'onepage/express/start');
			}
		});
	},
	
	initOnCart: function(){
		$j('.checkout-types .paypal-logo a, .opc-menu .paypal-logo a').click(function(e){		
			e.preventDefault();
			PAYPAL.apps.Checkout.startFlow(IWD.LIPP.config.baseUrl + 'onepage/express/start');
		});
	}	
};

$j(document).ready(function(){
	IWD.LIPP.init();
});