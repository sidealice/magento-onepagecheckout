;
//dummy
Billing =  Class.create();
Shipping =  Class.create();

// define jquery
if(typeof($j_opc) == 'undefined' || $j_opc == undefined || !$j_opc){
	$j_opc = false;
	
	if(typeof($ji) != 'undefined' && $ji != undefined && $ji)
		$j_opc = $ji; // from iwd_all 2.x
	else{
		if(typeof(jQuery) != 'undefined' && jQuery != undefined && jQuery)
			$j_opc = jQuery;
	}
}
///

var IWD=IWD||{};

IWD.OPC = {
		
		agreements : null,
		saveOrderStatus:false,
		is_subscribe:false,
		
		initMessages: function(){
			$j_opc('.close-message-wrapper, .opc-messages-action .button').click(function(){
				$j_opc('.opc-message-wrapper').hide();
				$j_opc('.opc-message-container').empty();
			});
		},

		/** CREATE EVENT FOR SAVE ORDER **/
		initSaveOrder: function(){
			
			$j_opc(document).on('click', '.opc-btn-checkout', function(){

				if (IWD.OPC.Checkout.disabledSave==true)
					return;

				// check agreements
				var mis_aggree = false;
				$j_opc('#checkout-agreements input[name*="agreement"]').each(function(){
					if(!$j_opc(this).is(':checked')){
						mis_aggree = true;
					}
				});
				
				if(mis_aggree){
					$j_opc('.opc-message-container').html($j_opc('#agree_error').html());
					$j_opc('.opc-message-wrapper').show();
					IWD.OPC.Checkout.hideLoader();
					IWD.OPC.Checkout.unlockPlaceOrder();
					IWD.OPC.saveOrderStatus = false;
					return false;
				}
				///
				
				var addressForm = new VarienForm('opc-address-form-billing');
				if (!addressForm.validator.validate()){
					return;
				}
				
				if (!$j_opc('input[name="billing[use_for_shipping]"]').prop('checked')){
					var addressForm = new VarienForm('opc-address-form-shipping');
					if (!addressForm.validator.validate()){				
						return;
					}
				}
				
				// check if LIPP enabled
			    if(typeof(IWD.LIPP) != 'undefined' && IWD.LIPP != undefined && IWD.LIPP != '' && IWD.LIPP)
			    {
					if(IWD.LIPP.lipp_enabled){
						var method = payment.currentMethod;
						if (method.indexOf('paypaluk_express')!=-1 || method.indexOf('paypal_express')!=-1){
							if (IWD.OPC.Checkout.config.comment!=="0")
								IWD.OPC.saveCustomerComment();							
							IWD.LIPP.redirectPayment();
							return;
						}
					}			    	
			    }
			    ////
				
				IWD.OPC.saveOrderStatus = true;
				IWD.OPC.Plugin.dispatch('saveOrderBefore');
				if (IWD.OPC.Checkout.isVirtual===false){
					IWD.OPC.Checkout.lockPlaceOrder();
					IWD.OPC.Shipping.saveShippingMethod();
				}else{
					IWD.OPC.validatePayment();
				}
			});
			
		},
		
		
		
		/** INIT CHAGE PAYMENT METHOD **/
		initPayment: function(){
			
			IWD.OPC.removeNotAllowedPaymentMethods();
			
			IWD.OPC.bindChangePaymentFields();
			$j_opc(document).on('click', '#co-payment-form input[type="radio"]', function(event){
				IWD.OPC.removeNotAllowedPaymentMethods();
				
				IWD.OPC.validatePayment();
			});
		},
		
		/** remove not allowed payment method **/
		removeNotAllowedPaymentMethods: function(){
			// remove p_method_authorizenet_directpost
			var auth_dp_obj = $j_opc('#p_method_authorizenet_directpost');
			if(auth_dp_obj && auth_dp_obj.attr('id') == 'p_method_authorizenet_directpost')
			{
				if(auth_dp_obj.attr('checked'))
					auth_dp_obj.attr('checked', false);
				
				auth_dp_obj.parent('dt').remove();
				$j_opc('#payment_form_authorizenet_directpost').parent('dd').remove();
				$j_opc('#directpost-iframe').remove();
				$j_opc('#co-directpost-form').remove();
			}
			////
		},
		
		/** CHECK PAYMENT IF PAYMENT IF CHECKED AND ALL REQUIRED FIELD ARE FILLED PUSH TO SAVE **/
		validatePayment: function(){	
			
			// check all required fields not empty
			var is_empty = false;
			$j_opc('#co-payment-form .required-entry').each(function(){
				if($j_opc(this).val() == '' && $j_opc(this).css('display') != 'none' && !$j_opc(this).attr('disabled'))
					is_empty = true;
			});

			if(!IWD.OPC.saveOrderStatus){
				if(is_empty){
					IWD.OPC.saveOrderStatus = false;
					IWD.OPC.Checkout.hideLoader();
					IWD.OPC.Checkout.unlockPlaceOrder();				
					return false;
				}
			}
			////

			var vp = payment.validate();
			if(!vp)
			{
				IWD.OPC.saveOrderStatus = false;
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				return false;
			}

			var paymentMethodForm = new Validation('co-payment-form', { onSubmit : false, stopOnFirst : false, focusOnError : false});
			  	
			if (paymentMethodForm.validate()){
				IWD.OPC.savePayment();
			}else{
				IWD.OPC.saveOrderStatus = false;
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				
				IWD.OPC.bindChangePaymentFields();
			}
			
			
		},
		
		/** BIND CHANGE PAYMENT FIELDS **/ 
		bindChangePaymentFields: function(){			
			IWD.OPC.unbindChangePaymentFields();
			
			$j_opc('#co-payment-form input').keyup(function(event){
				
				if (IWD.OPC.Checkout.ajaxProgress!=false){
					clearTimeout(IWD.OPC.Checkout.ajaxProgress);
				}
				
				IWD.OPC.Checkout.ajaxProgress = setTimeout(function(){
					IWD.OPC.validatePayment();
				}, 1000);
			});
			
			$j_opc('#co-payment-form select').change(function(event){
				if (IWD.OPC.Checkout.ajaxProgress!=false){
					clearTimeout(IWD.OPC.Checkout.ajaxProgress);
				}
				
				IWD.OPC.Checkout.ajaxProgress = setTimeout(function(){
					IWD.OPC.validatePayment();
				}, 1000);
			});
		},
		
		/** UNBIND CHANGE PAYMENT FIELDS **/
		unbindChangePaymentFields: function(){
			$j_opc('#co-payment-form input').unbind('keyup');
			$j_opc('#co-payment-form select').unbind('change');
		},
				
		
		/** SAVE PAYMENT **/		
		savePayment: function(){
			
			if (IWD.OPC.Checkout.xhr!=null){
				IWD.OPC.Checkout.xhr.abort();
			}
			
			IWD.OPC.Checkout.lockPlaceOrder();
			if (payment.currentMethod != 'stripe') {
				var form = $j_opc('#co-payment-form').serializeArray();
				
				IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
			}else{
			
				if (typeof(IWD.Stripe)!="undefined"){
					var nameValue = $('stripe_cc_owner').value;
					var numberValue = $('stripe_cc_number').value;
					var cvcValue =  $('stripe_cc_cid').value;
					var exp_monthValue = $('stripe_expiration').value;
					var exp_yearValue = $('stripe_expiration_yr').value;
				}else{
					//support stripe from ebizmets,				
					var nameValue = $('stripe_cc_owner').value;
					var numberValue = $('stripe_cc_number').value;
					var cvcValue =  $('stripe_cc_cvc').value;
					var exp_monthValue = $('stripe_cc_expiration_month').value;
					var exp_yearValue = $('stripe_cc_expiration_year').value;
				}
				
				
				Stripe.createToken({
					
					name: nameValue,
					number: numberValue,
					cvc: cvcValue,
					exp_month: exp_monthValue,
					exp_year: exp_yearValue
					
				}, function(status, response) {
					if (response.error) {
						IWD.OPC.Checkout.hideLoader();
						IWD.OPC.Checkout.xhr = null;
						IWD.OPC.Checkout.unlockPlaceOrder();
						alert(response.error.message);
					} else {
						
						if (typeof(IWD.Stripe)!="undefined"){
							var $form = $j_opc(IWD.Stripe.formId);
							$j_opc('#stripe_token').remove();
							$input = $j_opc('<input type="hidden" name="payment[stripe_token]" id="stripe_token" />').val(IWD.Stripe.token);
							$input.appendTo($form);
							
							$('stripe_token').value = response['id'];
							var form = $j_opc('#co-payment-form').serializeArray();
							IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
							
							
						}else{
							$('stripe_token').value = response['id'];
							var form = $j_opc('#co-payment-form').serializeArray();
							IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
						}
					}
				});
			}
		},
		
		/** CHECK RESPONSE FROM AJAX AFTER SAVE PAYMENT METHOD **/
		preparePaymentResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			
			IWD.OPC.agreements = $j_opc('#checkout-agreements').serializeArray();
			
			IWD.OPC.getSubscribe();

			if (typeof(response.review)!= "undefined"){
				IWD.OPC.Decorator.updateGrandTotal(response);
				$j_opc('#opc-review-block').html(response.review);
				IWD.OPC.Checkout.removePrice();
				
				// need to recheck subscribe and agreenet checkboxes
				IWD.OPC.recheckItems();
			}

			if (typeof(response.error) != "undefined"){
				
				IWD.OPC.Plugin.dispatch('error');
				
				$j_opc('.opc-message-container').html(response.error);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				IWD.OPC.saveOrderStatus = false;
				
				return;
			}

			//SOME PAYMENT METHOD REDIRECT CUSTOMER TO PAYMENT GATEWAY
			if (typeof(response.redirect) != "undefined" && IWD.OPC.saveOrderStatus===true){
				IWD.OPC.Checkout.xhr = null;
				IWD.OPC.Plugin.dispatch('redirectPayment', response.redirect);
				if (IWD.OPC.Checkout.xhr==null){
					setLocation(response.redirect);
				}
				else
				{
					IWD.OPC.Checkout.hideLoader();
					IWD.OPC.Checkout.unlockPlaceOrder();					
				}
				
				return;
			}
			
			if (IWD.OPC.saveOrderStatus===true){
				IWD.OPC.saveOrder();				
			}
			else
			{
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();				
			}
			
			IWD.OPC.Plugin.dispatch('savePaymentAfter');
			
			
		}, 
		
		/** SAVE ORDER **/
		saveOrder: function(){
			var form = $j_opc('#co-payment-form').serializeArray();
			form  = IWD.OPC.checkAgreement(form);
			form  = IWD.OPC.checkSubscribe(form);
			form  = IWD.OPC.getComment(form);
			
			IWD.OPC.Checkout.showLoader();
			IWD.OPC.Checkout.lockPlaceOrder();				

			if (IWD.OPC.Checkout.config.comment!=="0"){
				IWD.OPC.saveCustomerComment();
				
				setTimeout(function(){
					IWD.OPC.callSaveOrder(form);				
				},600);
			}
			else
			{
				IWD.OPC.callSaveOrder(form);
			}
		},
		
		callSaveOrder: function(form){
			IWD.OPC.Plugin.dispatch('saveOrder');
			IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.saveOrderUrl ,form, IWD.OPC.prepareOrderResponse,'json');			
		},
		
		/** SAVE CUSTOMER COMMNET **/
		saveCustomerComment: function(){
			$j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/comment',{"comment": $j_opc('#customer_comment').val()});
		}, 
		
		getComment: function(form){
			var com = $j_opc('#customer_comment').val();
			form.push({"name":"customer_comment", "value":com});
			return form;
		},
		
		/** ADD AGGREMENTS TO ORDER FORM **/
		checkAgreement: function(form){
			$j_opc.each(IWD.OPC.agreements, function(index, data){
				form.push(data);
			});
			return form;
		},
		
		/** ADD SUBSCRIBE TO ORDER FORM **/
		getSubscribe: function(){
			if ($j_opc('#is_subscribed').length){
				if ($j_opc('#is_subscribed').is(':checked'))
					IWD.OPC.is_subscribe = true;
				else
					IWD.OPC.is_subscribe = false;
			}
			else
				IWD.OPC.is_subscribe = false;			
		},
		
		checkSubscribe: function(form){
			
			if(IWD.OPC.is_subscribe)
				form.push({"name":"is_subscribed", "value":"1"});
			else
				form.push({"name":"is_subscribed", "value":"0"});

			return form;
		},
		
		/** Check checkboxes after refreshing review section **/
		recheckItems: function(){
			// check subscribe
			if ($j_opc('#is_subscribed').length){
				if(IWD.OPC.is_subscribe)
					$j_opc('#is_subscribed').prop('checked', true);
				else
					$j_opc('#is_subscribed').prop('checked', false);
			}
			
			// check agree
			IWD.OPC.recheckAgree();
		},
		
		recheckAgree: function(){			
			if(IWD.OPC.agreements != null){
				$j_opc.each(IWD.OPC.agreements, function(index, data){
					$j_opc('#checkout-agreements input').each(function(){
						if(data.name == $j_opc(this).prop('name'))
							$j_opc(this).prop('checked', true);
					});
				});
			}
		},
		
		/** CHECK RESPONSE FROM AJAX AFTER SAVE ORDER **/
		prepareOrderResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			if (typeof(response.error) != "undefined" && response.error!=false){
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();				
				
				IWD.OPC.saveOrderStatus = false;
				$j_opc('.opc-message-container').html(response.error);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.Plugin.dispatch('error');
				return;
			}
			
			if (typeof(response.error_messages) != "undefined" && response.error_messages!=false){
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();				
				
				IWD.OPC.saveOrderStatus = false;
				$j_opc('.opc-message-container').html(response.error_messages);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.Plugin.dispatch('error');
				return;
			}
			
		
			if (typeof(response.redirect) !="undefined"){
				if (response.redirect!==false){
					setLocation(response.redirect);
					return;
				}
			}
			
			if (typeof(response.update_section) != "undefined"){
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();				
				
				//create catch for default logic  - for not spam errors to console
				try{
					$j_opc('#checkout-' + response.update_section.name + '-load').html(response.update_section.html);
				}catch(e){
					
				}
				
				IWD.OPC.prepareExtendPaymentForm();
				$j_opc('#payflow-advanced-iframe').show();
				$j_opc('#payflow-link-iframe').show();
				$j_opc('#hss-iframe').show();
				
			}
			IWD.OPC.Checkout.hideLoader();
			IWD.OPC.Checkout.unlockPlaceOrder();				
			
			IWD.OPC.Plugin.dispatch('responseSaveOrder', response);
		},
		
		
};



IWD.OPC.Checkout = {
		config:null,
		ajaxProgress:false,
		xhr: null,
		isVirtual: false,
		disabledSave: false,
		saveOrderUrl: null,
		xhr2: null,
		updateShippingPaymentProgress: false,
		
		init:function(){		
			
			if (this.config==null){
				return;
			}
			//base config
			this.config = $j_opc.parseJSON(this.config);
			
			IWD.OPC.Checkout.saveOrderUrl = IWD.OPC.Checkout.config.baseUrl + 'onepage/json/saveOrder',
			this.success = IWD.OPC.Checkout.config.baseUrl + 'checkout/onepage/success',
			
			//DECORATE
			this.clearOnChange();
			this.removePrice();			
			
			//MAIN FUNCTION
			IWD.OPC.Billing.init();
			IWD.OPC.Shipping.init();	
			IWD.OPC.initMessages();
			IWD.OPC.initSaveOrder();
			
			
			if (this.config.isLoggedIn===1){
				var addressId = $j_opc('#billing-address-select').val();
				if (addressId!='' && addressId!=undefined ){
					IWD.OPC.Billing.save();
				}else{
					//FIX FOR MAGENTO 1.8 - NEED LOAD PAYTMENT METHOD BY AJAX
					IWD.OPC.Checkout.pullPayments();
				}
			}else{
				//FIX FOR MAGENTO 1.8 - NEED LOAD PAYTMENT METHOD BY AJAX
				IWD.OPC.Checkout.pullPayments();
			}
			
			IWD.OPC.initPayment();
		},
		
		/** PARSE RESPONSE FROM AJAX SAVE BILLING AND SHIPPING METHOD **/
		prepareAddressResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			
			if (typeof(response.error) != "undefined"){
				$j_opc('.opc-message-container').html(response.message);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				return;
			}
			
			/* IWD ADDRESS VALIDATION  */
            if (typeof(response.address_validation) != "undefined"){
                $j_opc('#checkout-address-validation-load').empty().html(response.address_validation);
                IWD.OPC.Checkout.hideLoader();
                IWD.OPC.Checkout.unlockPlaceOrder();
                return;
            }
			
			if (typeof(response.shipping) != "undefined"){
				$j_opc('#shipping-block-methods').empty().html(response.shipping);
			}
			
			if (typeof(response.payments) != "undefined"){
				$j_opc('#checkout-payment-method-load').empty().html(response.payments);
				
				IWD.OPC.removeNotAllowedPaymentMethods();
				payment.initWhatIsCvvListeners();//default logic for view "what is this?"
			}
			
			if (typeof(response.isVirtual) != "undefined"){
				IWD.OPC.Checkout.isVirtual = true;
			}
			
			if (IWD.OPC.Checkout.isVirtual===false){
				var update_payments = false;
				if (typeof(response.reload_payments) != "undefined")
					update_payments = true;
				
				var reload_totals = false;
				if (typeof(response.reload_totals) != "undefined")
					reload_totals = true;
				
				IWD.OPC.Shipping.saveShippingMethod(update_payments, reload_totals);
				
			}else{
				$j_opc('.shipping-block').hide();
				$j_opc('.payment-block').addClass('clear-margin');
				IWD.OPC.Checkout.pullPayments();
			}
		},
		
		/** PARSE RESPONSE FROM AJAX SAVE SHIPPING METHOD **/
		prepareShippingMethodResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			if (typeof(response.error)!="undefined"){
				
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				
				IWD.OPC.Plugin.dispatch('error');
				
				$j_opc('.opc-message-container').html(response.message);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.saveOrderStatus = false;
				return;
			}
			
			if (typeof(response.review)!="undefined" && IWD.OPC.saveOrderStatus===false){
				try{
					IWD.OPC.Decorator.updateGrandTotal(response);
					$j_opc('#opc-review-block').html(response.review);
				}catch(e){
					
				}
				IWD.OPC.Checkout.removePrice();
				
//				IWD.OPC.recheckAgree();
			}
			
			
			
			//IF STATUS TRUE - START SAVE PAYMENT FOR CREATE ORDER
			if (IWD.OPC.saveOrderStatus==true){
				IWD.OPC.validatePayment();
			}else{
				IWD.OPC.Checkout.pullPayments();
			}
		},
		
		
		clearOnChange: function(){
			$j_opc('.opc-col-left input, .opc-col-left select').removeAttr('onclick').removeAttr('onchange');
		},
		
		removePrice: function(){
			
			$j_opc('.opc-data-table tr th:nth-child(2)').remove();
			$j_opc('.opc-data-table tbody tr td:nth-child(2)').remove();
			$j_opc('.opc-data-table tfoot td').each(function(){
				var colspan = $j_opc(this).attr('colspan');
				
				if (colspan!="" && colspan !=undefined){
					colspan = parseInt(colspan) - 1;
					$j_opc(this).attr('colspan', colspan);
				}
			});
			
			$j_opc('.opc-data-table tfoot th').each(function(){
				var colspan = $j_opc(this).attr('colspan');
				
				if (colspan!="" && colspan !=undefined){
					colspan = parseInt(colspan) - 1;
					$j_opc(this).attr('colspan', colspan);
				}
			});
			
		},
		
		showLoader: function(){
			$j_opc('.opc-ajax-loader').show();
			//$j_opc('.opc-btn-checkout').addClass('button-disabled');
		},
		
		hideLoader: function(){
			setTimeout(function(){
				$j_opc('.opc-ajax-loader').hide();
				//$j_opc('.opc-btn-checkout').removeClass('button-disabled');				
			},600);
		},
		
		/** APPLY SHIPPING METHOD FORM TO BILLING FORM **/
		applyShippingMethod: function(form){
			formShippimgMethods = $j_opc('#opc-co-shipping-method-form').serializeArray();
			$j_opc.each(formShippimgMethods, function(index, data){
				form.push(data);
			});
			
			return form;
		},
		
		/** APPLY NEWSLETTER TO BILLING FORM **/
		applySubscribed: function(form){
			if ($j_opc('#is_subscribed').length){
				if ($j_opc('#is_subscribed').is(':checked')){
					form.push({"name":"is_subscribed", "value":"1"});
				}
			}
			
			return form;
		},
		
		/** PULL REVIEW **/
		pullReview: function(){
			IWD.OPC.Checkout.lockPlaceOrder();
			IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/review',function(response){
				IWD.OPC.Checkout.xhr = null;
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				if (typeof(response.review)!="undefined"){
					IWD.OPC.Decorator.updateGrandTotal(response);
					$j_opc('#opc-review-block').html(response.review);
					
					IWD.OPC.Checkout.removePrice();
					
//					IWD.OPC.recheckAgree();
				}
				IWD.OPC.removeNotAllowedPaymentMethods();
			});
		},
		
		/** PULL PAYMENTS METHOD AFTER LOAD PAGE **/
		pullPayments: function(){
			IWD.OPC.Checkout.lockPlaceOrder();
			IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/payments',function(response){
				IWD.OPC.Checkout.xhr = null;
				
				if (typeof(response.error)!="undefined"){
					$j_opc('.opc-message-container').html(response.error);
					$j_opc('.opc-message-wrapper').show();
					IWD.OPC.saveOrderStatus = false;
					IWD.OPC.Checkout.hideLoader();
					IWD.OPC.Checkout.unlockPlaceOrder();					
					return;
				}
				
				if (typeof(response.payments)!="undefined"){
					$j_opc('#checkout-payment-method-load').html(response.payments);
					
					payment.initWhatIsCvvListeners();
					IWD.OPC.bindChangePaymentFields();
					IWD.OPC.Decorator.setCurrentPaymentActive();
				};
				
				IWD.OPC.Checkout.pullReview();
				
			},'json');
		},
		
		lockPlaceOrder: function(mode){
			if(typeof(mode)=='undefined' || mode == undefined || !mode)
				mode = 0;
			if(mode == 0)
				$j_opc('.opc-btn-checkout').addClass('button-disabled');
			IWD.OPC.Checkout.disabledSave = true;
		},
		
		unlockPlaceOrder: function(){
			$j_opc('.opc-btn-checkout').removeClass('button-disabled');
			IWD.OPC.Checkout.disabledSave = false;
		},
	
		abortAjax: function(){
			if (IWD.OPC.Checkout.xhr!=null){
				IWD.OPC.Checkout.xhr.abort();
				
				IWD.OPC.saveOrderStatus = false;
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
			}
		},
		
		reloadShippingsPayments: function(form_type, delay){
			if(typeof(delay) == 'undefined' || delay == undefined || !delay)
				delay = 1400;
			
			clearTimeout(IWD.OPC.Checkout.updateShippingPaymentProgress);
			
			IWD.OPC.Checkout.updateShippingPaymentProgress = setTimeout(function(){
				
				var form = $j_opc('#opc-address-form-'+form_type).serializeArray();
				form = IWD.OPC.Checkout.applyShippingMethod(form);
				
				if (IWD.OPC.Checkout.xhr2!=null)
					IWD.OPC.Checkout.xhr2.abort();
				
				IWD.OPC.Checkout.xhr2 = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/reloadShippingsPayments',form, IWD.OPC.Checkout.reloadShippingsPaymentsResponse,'json');
				
			}, delay);
			
		},
		
		reloadShippingsPaymentsResponse: function(response){
			
			IWD.OPC.Checkout.xhr2 = null;
			
			if (typeof(response.error) != "undefined"){
				$j_opc('.opc-message-container').html(response.message);
				$j_opc('.opc-message-wrapper').show();
				IWD.OPC.Checkout.hideLoader();
				IWD.OPC.Checkout.unlockPlaceOrder();
				return;
			}
			
			if (typeof(response.shipping) != "undefined"){
				$j_opc('#shipping-block-methods').empty().html(response.shipping);
			}
			
			if (typeof(response.payments) != "undefined"){
				
				if(response.payments != ''){
					$j_opc('#checkout-payment-method-load').empty().html(response.payments);

					IWD.OPC.removeNotAllowedPaymentMethods();
					payment.initWhatIsCvvListeners();//default logic for view "what is this?"
				}

				if (IWD.OPC.Checkout.isVirtual===false){
					var update_payments = false;
					if (typeof(response.reload_payments) != "undefined")
						update_payments = true;

					IWD.OPC.Shipping.saveShippingMethod(update_payments);
				}else{
					$j_opc('.shipping-block').hide();
					$j_opc('.payment-block').addClass('clear-margin');
					IWD.OPC.Checkout.pullPayments();
				}
			}
			else{
				if(typeof(response.reload_totals) != "undefined")
					IWD.OPC.Checkout.pullReview();
			}
		},
		
		checkRunReloadShippingsPayments: function(address_type){
			var zip = $j_opc('#'+address_type+':postcode').val();
			var country = $j_opc('#'+address_type+':country_id').val();
			var region = $j_opc('#'+address_type+':region_id').val();
			
			if(zip != '' || country != '' || region != '')
				IWD.OPC.Checkout.reloadShippingsPayments(address_type);
		}
};


IWD.OPC.Billing = {
		bill_need_update: true,
		need_reload_shippings_payments: false,
		validate_timeout: false,
		
		init: function(){
			IWD.OPC.Billing.bill_need_update = true;

			//set flag use billing for shipping and init change flag
			var use_for_ship = false;
			var el = $j_opc('input[name="billing[use_for_shipping]"]');
			if(typeof(el) != 'undefined' && el != undefined && el){
				if(el.prop('type') == 'checkbox'){
					if(el.is(':checked'))
						use_for_ship = true;
				}
				else
					use_for_ship = true;
			}
			else
				use_for_ship = true;

			if(use_for_ship)
				this.setBillingForShipping(true);
			else
				this.setBillingForShipping(false, true);
			////
			
			$j_opc('input[name="billing[use_for_shipping]"]').change(function(){
				if ($j_opc(this).is(':checked')){
					IWD.OPC.Billing.setBillingForShipping(true);
					$j_opc('#opc-address-form-billing select[name="billing[country_id]"]').change();
					IWD.OPC.Billing.need_reload_shippings_payments = 'billing';
					IWD.OPC.Billing.validateForm();
				}else{
					IWD.OPC.Billing.setBillingForShipping(false);
					IWD.OPC.Billing.need_reload_shippings_payments = 'shipping';
					IWD.OPC.Shipping.validateForm();
				}
			});
			
			
			//update password field
			$j_opc('input[name="billing[create_account]"]').click(function(){
				if ($j_opc(this).is(':checked')){
					$j_opc('#register-customer-password').removeClass('hidden');
					$j_opc('input[name="billing[customer_password]"]').addClass('required-entry');
					$j_opc('input[name="billing[confirm_password]"]').addClass('required-entry');
				}else{
					$j_opc('#register-customer-password').addClass('hidden');
					$j_opc('input[name="billing[customer_password]"]').removeClass('required-entry');
					$j_opc('input[name="billing[confirm_password]"]').removeClass('required-entry');
					$j_opc('#register-customer-password input').val('');
				}
			});
			
			this.initChangeAddress();
			this.initChangeSelectAddress();
		},
		
		/** CREATE EVENT FOR UPDATE SHIPPING BLOCK **/
		initChangeAddress: function(){

			$j_opc('#opc-address-form-billing input').blur(function(){
				if(IWD.OPC.Billing.bill_need_update)
					IWD.OPC.Billing.validateForm();
			});

			$j_opc('#opc-address-form-billing').mouseleave(function(){
				if(IWD.OPC.Billing.bill_need_update)
					IWD.OPC.Billing.validateForm();
			});
			
			$j_opc('#opc-address-form-billing input').keydown(function(){
				IWD.OPC.Billing.bill_need_update = true;
				clearTimeout(IWD.OPC.Checkout.ajaxProgress);
				IWD.OPC.Checkout.abortAjax();
				
				// check if zip
				var el_id = $j_opc(this).attr('id');
				if(el_id == 'billing:postcode')
					IWD.OPC.Checkout.reloadShippingsPayments('billing');

				IWD.OPC.Billing.validateForm(3000);
			});
			
			$j_opc('#opc-address-form-billing select').not('#billing-address-select').change(function(){
				// check if country
				var el_id = $j_opc(this).attr('id');
				if(el_id == 'billing:country_id' || el_id == 'billing:region_id')
					IWD.OPC.Checkout.reloadShippingsPayments('billing', 800);
				
				IWD.OPC.Billing.bill_need_update = true;
				IWD.OPC.Billing.validateForm();
			});			
		},
		
		validateForm: function(delay){
			clearTimeout(IWD.OPC.Billing.validate_timeout);
			if(typeof(delay) == 'undefined' || delay == undefined || !delay)
				delay = 100;
			
			IWD.OPC.Billing.validate_timeout = setTimeout(function(){
				var mode = IWD.OPC.Billing.need_reload_shippings_payment;
				IWD.OPC.Billing.need_reload_shippings_payment = false;

				var valid = IWD.OPC.Billing.validateAddressForm();
				if (valid){
					IWD.OPC.Billing.save();
				}
				else{
					if(mode != false)
						IWD.OPC.Checkout.checkRunReloadShippingsPayments(mode);
				}
			},delay);
		},
		
		
		/** CREATE EVENT FOR CHANGE ADDRESS TO NEW OR FROM ADDRESS BOOK **/
		initChangeSelectAddress: function(){
			$j_opc('#billing-address-select').change(function(){
				if ($j_opc(this).val()==''){
					$j_opc('#billing-new-address-form').show();
				}else{
					$j_opc('#billing-new-address-form').hide();
					IWD.OPC.Billing.validateForm();
				}
			});
			
			
		},
		
		/** VALIDATE ADDRESS BEFORE SEND TO SAVE QUOTE**/
		validateAddressForm: function(form){
			// check all required fields not empty
			var is_empty = false;
			$j_opc('#opc-address-form-billing .required-entry').each(function(){
				if($j_opc(this).val() == '' && $j_opc(this).css('display') != 'none' && !$j_opc(this).attr('disabled'))
					is_empty = true;
			});
			if(is_empty)
				return false;
			////

			var addressForm = new Validation('opc-address-form-billing', { onSubmit : false, stopOnFirst : false, focusOnError : false});
			if (addressForm.validate()){				  		 
				return true;
			}else{				 
				return false;
			}
		},
		
		/** SET SHIPPING AS BILLING TO TRUE OR FALSE **/
		setBillingForShipping:function(useBilling, skip_copy){
			if (useBilling==true){
				$j_opc('input[name="billing[use_for_shipping]"]').prop('checked', true);
				$j_opc('input[name="shipping[same_as_billing]"]').prop('checked', true);
				$j_opc('#opc-address-form-shipping').addClass('hidden');				
			}else{
				if(typeof(skip_copy) == 'undefined' || skip_copy == undefined)
					skip_copy = false
				if(!skip_copy)
					this.pushBilingToShipping();	
				$j_opc('input[name="billing[use_for_shipping]"]').prop('checked', false);
				$j_opc('input[name="shipping[same_as_billing]"]').prop('checked', false);
				$j_opc('#opc-address-form-shipping').removeClass('hidden');
			}
			
		}, 
		
		/** COPY FIELD FROM BILLING FORM TO SHIPPING **/
		pushBilingToShipping:function(clearShippingForm){
			//pull country
			var valueCountry = $j_opc('#billing-new-address-form select[name="billing[country_id]"]').val();
			$j_opc('#opc-address-form-shipping  select[name="shipping[country_id]"] [value="' + valueCountry + '"]').prop("selected", true);	
			shippingRegionUpdater.update();
			
			
			//pull region id
			var valueRegionId = $j_opc('#billing-new-address-form select[name="billing[region_id]"]').val();
			$j_opc('#opc-address-form-shipping  select[name="shipping[region_id]"] [value="' + valueRegionId + '"]').prop("selected", true);
			
			//pull other fields	
			$j_opc('#billing-new-address-form input').not(':hidden, :input[type="checkbox"]').each(function(){
				var name = $j_opc(this).attr('name');
				var value = $j_opc(this).val();
				var shippingName =  name.replace( /billing/ , 'shipping');
				
				$j_opc('#opc-address-form-shipping input[name="'+shippingName+'"]').val(value);

			});
			
			//pull address field
			$j_opc('#billing-new-address-form input[name="billing[street][]"]').each(function(indexBilling){
				var valueAddress = $j_opc(this).val();
				$j_opc('#opc-address-form-shipping input[name="shipping[street][]"]').each(function(indexShipping){
					if (indexBilling==indexShipping){
						$j_opc(this).val(valueAddress);
					}
				});				
			});
			
			//init trigger change shipping form
			$j_opc('#opc-address-form-shipping select[name="shipping[country_id]"]').change();
		},

		/** METHOD CREATE AJAX REQUEST FOR UPDATE BILLING ADDRESS **/
		save: function(){
			if (IWD.OPC.Checkout.ajaxProgress!=false){
				clearTimeout(IWD.OPC.Checkout.ajaxProgress);
			}

			// stop reload shippings/payments logic
			if (IWD.OPC.Checkout.updateShippingPaymentProgress!=false)
				clearTimeout(IWD.OPC.Checkout.updateShippingPaymentProgress);
			
			if (IWD.OPC.Checkout.xhr2!=null)
				IWD.OPC.Checkout.xhr2.abort();
			////
			
			IWD.OPC.Checkout.ajaxProgress = setTimeout(function(){
					var form = $j_opc('#opc-address-form-billing').serializeArray();
					form = IWD.OPC.Checkout.applyShippingMethod(form);					
					form = IWD.OPC.Checkout.applySubscribed(form); 
					
					if (IWD.OPC.Checkout.xhr!=null){
						IWD.OPC.Checkout.xhr.abort();
					}
					
					if($j_opc('input[name="billing[use_for_shipping]"]').is(':checked'))
						IWD.OPC.Checkout.showLoader();
					else
						IWD.OPC.Checkout.lockPlaceOrder(1);
					
					IWD.OPC.Billing.bill_need_update = false;		
					IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/saveBilling',form, IWD.OPC.Checkout.prepareAddressResponse,'json');
			}, 500);
		},
		
};

IWD.OPC.Shipping = {
		ship_need_update: true,
		validate_timeout: false,
		
		init: function(){
			IWD.OPC.Shipping.ship_need_update = true;
			this.initChangeAddress();
			this.initChangeSelectAddress();
			this.initChangeShippingMethod();
		},

		/** CREATE EVENT FOR UPDATE SHIPPING BLOCK **/
		initChangeAddress: function(){
			
			$j_opc('#opc-address-form-shipping input').blur(function(){
				if(IWD.OPC.Shipping.ship_need_update)
					IWD.OPC.Shipping.validateForm();
			});

			$j_opc('#opc-address-form-shipping').mouseleave(function(){
				if(IWD.OPC.Shipping.ship_need_update)
					IWD.OPC.Shipping.validateForm();
			});
			
			$j_opc('#opc-address-form-shipping input').keydown(function(){
				IWD.OPC.Shipping.ship_need_update = true;
				clearTimeout(IWD.OPC.Checkout.ajaxProgress);
				IWD.OPC.Checkout.abortAjax();

				// check if zip
				var el_id = $j_opc(this).attr('id');
				if(el_id == 'shipping:postcode')
					IWD.OPC.Checkout.reloadShippingsPayments('shipping');

				IWD.OPC.Shipping.validateForm(3000);
				
			});
			
			$j_opc('#opc-address-form-shipping select').not('#shipping-address-select').change(function(){
				// check if country
				var el_id = $j_opc(this).attr('id');
				if(el_id == 'shipping:country_id' || el_id == 'shipping:region_id')
					IWD.OPC.Checkout.reloadShippingsPayments('shipping', 800);
				
				IWD.OPC.Shipping.ship_need_update = true;
				IWD.OPC.Shipping.validateForm();
			});
		},
		
		/** CREATE VENT FOR CHANGE ADDRESS TO NEW OR FROM ADDRESS BOOK **/
		initChangeSelectAddress: function(){
			$j_opc('#shipping-address-select').change(function(){
				if ($j_opc(this).val()==''){
					$j_opc('#shipping-new-address-form').show();
				}else{
					$j_opc('#shipping-new-address-form').hide();
					IWD.OPC.Shipping.validateForm();
				}
			});
			
			
		},
		
		//create observer for change shipping method. 
		initChangeShippingMethod: function(){
			$j_opc('.opc-wrapper-opc #shipping-block-methods').on('change', 'input[type="radio"]', function(){
				IWD.OPC.Shipping.saveShippingMethod();
			});
		},
		
		validateForm: function(delay){
			clearTimeout(IWD.OPC.Shipping.validate_timeout);
			if(typeof(delay) == 'undefined' || delay == undefined || !delay)
				delay = 100;
			
			IWD.OPC.Shipping.validate_timeout = setTimeout(function(){
				var mode = IWD.OPC.Billing.need_reload_shippings_payment;
				IWD.OPC.Billing.need_reload_shippings_payment = false;

				var valid = IWD.OPC.Shipping.validateAddressForm();
				if (valid){
					IWD.OPC.Shipping.save();
				}
				else{
					if(mode != false)
						IWD.OPC.Checkout.checkRunReloadShippingsPayments(mode);
				}
			},delay);
		},
		
		/** VALIDATE ADDRESS BEFORE SEND TO SAVE QUOTE**/
		validateAddressForm: function(form){
			// check all required fields not empty
			var is_empty = false;
			$j_opc('#opc-address-form-shipping .required-entry').each(function(){
				if($j_opc(this).val() == '' && $j_opc(this).css('display') != 'none' && !$j_opc(this).attr('disabled'))
					is_empty = true;
			});
			
			if(is_empty)
				return false;
			////
			
			var addressForm = new Validation('opc-address-form-shipping', { onSubmit : false, stopOnFirst : false, focusOnError : false});
			if (addressForm.validate()){				  		 
				return true;
			}else{				 
				return false;
			}
		},
		
		/** METHOD CREATE AJAX REQUEST FOR UPDATE SHIPPIN METHOD **/
		save: function(){
			if (IWD.OPC.Checkout.ajaxProgress!=false){
				clearTimeout(IWD.OPC.Checkout.ajaxProgress);
			}
			
			// stop reload shippings/payments logic
			if (IWD.OPC.Checkout.updateShippingPaymentProgress!=false)
				clearTimeout(IWD.OPC.Checkout.updateShippingPaymentProgress);
			
			if (IWD.OPC.Checkout.xhr2!=null)
				IWD.OPC.Checkout.xhr2.abort();
			////
			
			IWD.OPC.Checkout.ajaxProgress = setTimeout(function(){
					var form = $j_opc('#opc-address-form-shipping').serializeArray();
					form = IWD.OPC.Checkout.applyShippingMethod(form);
					if (IWD.OPC.Checkout.xhr!=null){
						IWD.OPC.Checkout.xhr.abort();
					}
					IWD.OPC.Checkout.lockPlaceOrder(1);
					
					IWD.OPC.Shipping.ship_need_update = false;
					IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/saveShipping',form, IWD.OPC.Checkout.prepareAddressResponse,'json');
			}, 500);
		},
		
		saveShippingMethod: function(update_payments, reload_totals){
			
			if (IWD.OPC.Shipping.validateShippingMethod()===false){

				if (IWD.OPC.saveOrderStatus){	
					$j_opc('.opc-message-container').html($j_opc('#pssm_msg').html());
					$j_opc('.opc-message-wrapper').show();
				}
				IWD.OPC.saveOrderStatus = false;
					
				IWD.OPC.Checkout.hideLoader();
				
				if(typeof(update_payments) != 'undefined' && update_payments != undefined && update_payments) // if was request to reload payments
					IWD.OPC.Checkout.pullPayments();
				else{
					if(typeof(reload_totals) == 'undefined' || reload_totals == undefined)
						reload_totals = false;
					
					if(reload_totals)
						IWD.OPC.Checkout.pullReview();
					else
						IWD.OPC.Checkout.unlockPlaceOrder();
				}
				
				return;
			}
			
			if (IWD.OPC.Checkout.ajaxProgress!=false){
				clearTimeout(IWD.OPC.Checkout.ajaxProgress);
			}
			
			IWD.OPC.Checkout.ajaxProgress = setTimeout(function(){
				var form = $j_opc('#opc-co-shipping-method-form').serializeArray();
				form = IWD.OPC.Checkout.applySubscribed(form); 
				if (IWD.OPC.Checkout.xhr!=null){
					IWD.OPC.Checkout.xhr.abort();
				}
				IWD.OPC.Checkout.showLoader();
				IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/saveShippingMethod',form, IWD.OPC.Checkout.prepareShippingMethodResponse);
			}, 600);
		},
		
		validateShippingMethod: function(){			
			var shippingChecked = false;
			$j_opc('#opc-co-shipping-method-form input').each(function(){				
				if ($j_opc(this).prop('checked')){							
					shippingChecked =  true;
				}
			});
			
			return shippingChecked;
		}		
};


IWD.OPC.Coupon = {
		init: function(){
			
			$j_opc(document).on('click', '.apply-coupon', function(){
				IWD.OPC.Coupon.applyCoupon(false);
			});
			
			
			$j_opc(document).on('click', '.remove-coupon', function(){
				IWD.OPC.Coupon.applyCoupon(true);
			});
			
			
			$j_opc(document).on('click','.discount-block h3', function(){
				if ($j_opc(this).hasClass('open-block')){
					$j_opc(this).removeClass('open-block');
					$j_opc(this).next().addClass('hidden');
				}else{
					$j_opc(this).addClass('open-block');					
					$j_opc(this).next().removeClass('hidden');
				}
			});
			
		},
		
		applyCoupon: function(remove){
			
			var form = $j_opc('#opc-discount-coupon-form').serializeArray();
			if (remove===false){				
				form.push({"name":"remove", "value":"0"});
			}else{
				form.push({"name":"remove", "value":"1"});
			}
			
			IWD.OPC.Checkout.showLoader();
			IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/coupon/couponPost',form, IWD.OPC.Coupon.prepareResponse,'json');
		},
		
		prepareResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			IWD.OPC.Checkout.hideLoader();
			if (typeof(response.message) != "undefined"){
				$j_opc('.opc-message-container').html(response.message);
				$j_opc('.opc-message-wrapper').show();
				
				IWD.OPC.Checkout.pullReview();
			}
			if (typeof(response.coupon) != "undefined"){
				$j_opc('#opc-discount-coupon-form').replaceWith(response.coupon).show();				
				$j_opc('#opc-discount-coupon-form').show();
			}
			if (typeof(response.payments)!="undefined"){
				$j_opc('#checkout-payment-method-load').html(response.payments);
				
				IWD.OPC.removeNotAllowedPaymentMethods();
				
				payment.initWhatIsCvvListeners();
				IWD.OPC.bindChangePaymentFields();
			};			
		}
};

IWD.OPC.Comment = {
		init: function(){
			
			$j_opc(document).on('click','.comment-block h3', function(){
				if ($j_opc(this).hasClass('open-block')){
					$j_opc(this).removeClass('open-block');
					$j_opc(this).next().addClass('hidden');
				}else{
					$j_opc(this).addClass('open-block');					
					$j_opc(this).next().removeClass('hidden');
				}
			});
		}
};
			
IWD.OPC.SignatureAtCheckout = {
    init: function(){
        $j_opc(document).on('click','.signature-block h3', function(){
            if ($j_opc(this).hasClass('open-block')){
                $j_opc(this).removeClass('open-block');
                $j_opc(this).next().addClass('hidden');
            }else{
                $j_opc(this).addClass('open-block');
                $j_opc(this).next().removeClass('hidden');
            }
        });

    }
};

IWD.OPC.Agreement ={
		
		init: function(){
			
			$j_opc(document).on('click', '.view-agreement', function(e){
				e.preventDefault();
				$j_opc('.opc-review-actions #modal-agreement').addClass('md-show');
				
				var id = $j_opc(this).data('id');
				var title = $j_opc(this).html();
				var content = $j_opc('.opc-review-actions #agreement-block-'+id).html();
				
				$j_opc('.opc-review-actions #agreement-title').html(title);
				$j_opc('.opc-review-actions #agreement-modal-body').html(content);
			});
			
			$j_opc(document).on('click', '#checkout-agreements input[name*="agreement"]', function(){
				var cur_el = $j_opc(this);
				$j_opc('#checkout-agreements input').each(function(){
					
					if(cur_el.prop('name') == $j_opc(this).prop('name')){
						$j_opc(this).prop('checked', cur_el.prop('checked'));
					}
				});
				
				// save agreements statuses
				IWD.OPC.agreements = $j_opc('#checkout-agreements').serializeArray();
			});
		}
};

IWD.OPC.Login ={
		
		init: function(){
			$j_opc('.login-trigger').click(function(e){
				e.preventDefault();
				$j_opc('#modal-login').addClass('md-show');
			});
			
			$j_opc(document).on('click','.md-modal .close', function(e){
				e.preventDefault();
				$j_opc('.md-modal').removeClass('md-show');
			});
			
			$j_opc(document).on('click', '.restore-account', function(e){
				e.preventDefault();
				$j_opc('#login-form').hide();$j_opc('#login-button-set').hide();
				$j_opc('#form-validate-email').fadeIn();$j_opc('#forgotpassword-button-set').show();
			});
			
			
			$j_opc('#login-button-set .btn').click(function(){
				$j_opc('#login-form').submit();
			});
			
			$j_opc('#forgotpassword-button-set .btn').click(function(){
				var form = $j_opc('#form-validate-email').serializeArray();
				IWD.OPC.Checkout.showLoader();
				IWD.OPC.Checkout.xhr = $j_opc.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/forgotpassword',form, IWD.OPC.Login.prepareResponse,'json');
			});
			
			
			$j_opc('#forgotpassword-button-set .back-link').click(function(e){
				e.preventDefault();
				$j_opc('#form-validate-email').hide();$j_opc('#forgotpassword-button-set').hide();
				$j_opc('#login-form').fadeIn();$j_opc('#login-button-set').show();
				
			});
			
			// if persistent, show login form
			if($j_opc('.opc-login-trigger').hasClass('is_persistent')){
				$j_opc('.signin-modal').trigger('click');
			}
			
		},
		
		prepareResponse: function(response){
			IWD.OPC.Checkout.xhr = null;
			IWD.OPC.Checkout.hideLoader();
			if (typeof(response.error)!="undefined"){
				alert(response.message);
			}else{
				alert(response.message);
				$j_opc('#forgotpassword-button-set .back-link').click();
			}
		}
};

IWD.OPC.Decorator = {
		initReviewBlock: function(){
			$j_opc('a.review-total').click(function(){
				if ($j_opc(this).hasClass('open')){
					$j_opc(this).removeClass('open')
					$j_opc('#opc-review-block').addClass('hidden');
				}else{
					$j_opc(this).addClass('open')
					$j_opc('#opc-review-block').removeClass('hidden');
				}
			});
		},
		updateGrandTotal: function(response){
			$j_opc('.opc-review-actions h5 span').html(response.grandTotal);
			$j_opc('.review-total span').html(response.grandTotal);
		},
		
		setActivePayment: function(){
			//check and setup current active method 
			this.setCurrentPaymentActive();
			
			$j_opc(document).on('click','#checkout-payment-method-load dt', function(){
				$j_opc('#checkout-payment-method-load dt').removeClass('active');
				$j_opc(this).addClass('active');
			});
		},
		
		setCurrentPaymentActive: function(){
			var method = payment.currentMethod;
			$j_opc('#p_method_'+method).parent().addClass('active');
		}
};

$j_opc(document).ready(function(){
	IWD.OPC.Checkout.init();
	IWD.OPC.Coupon.init();
	IWD.OPC.Comment.init();
	IWD.OPC.Agreement.init();
	IWD.OPC.Login.init();
	IWD.OPC.Decorator.initReviewBlock();
	IWD.OPC.Decorator.setActivePayment();
});
