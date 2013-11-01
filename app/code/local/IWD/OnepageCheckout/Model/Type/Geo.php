<?php
include("MaxMind/GeoIP/geoip.inc");
include("MaxMind/GeoIP/geoipcity.inc");
include("MaxMind/GeoIP/geoipregionvars.php");

class IWD_OnepageCheckout_Model_Type_Geo
{
	const CUSTOMER = 'customer';
    const GUEST    = 'guest';
    const REGISTER = 'register';
    
    protected $_help_obj;
    protected $_quote_obj;
    private $_em_ex_msg = '';
    protected $_cust_sess;
    protected $_check_sess;
    
    protected $verification_lib	= 'ups';

    public function __construct()
    {
        $this->_help_obj	= Mage::helper('onepagecheckout');
        $this->_em_ex_msg = $this->_help_obj->__('This email adress is already registered. Please enter another email to register account or login using this email.');
        $this->_check_sess = Mage::getSingleton('checkout/session');
        $this->_quote_obj	= $this->_check_sess->getQuote();
        
        $this->_cust_sess = Mage::getSingleton('customer/session');
    }

    public function getQuote()
    {
        return $this->_quote_obj;
    }
    
    public function getCustomerSession()
    {
        return $this->_cust_sess;
    }
    
    public function getCheckout()
    {
        return $this->_check_sess;
    }

    protected function _PaymentMethodAllowed($pmnt_method)
    {
        if ($pmnt_method->canUseForCountry($this->getQuote()->getBillingAddress()->getCountry()))
        {
			$grand_total= $this->getQuote()->getBaseGrandTotal();
			$min		= $pmnt_method->getConfigData('min_order_total');
			$max		= $pmnt_method->getConfigData('max_order_total');

			if((!empty($max) && ($grand_total > $max)) || (!empty($min) && ($grand_total < $min)))
				return false;
        
			return true;
        }
        else
        	return false;
    }

    public function initDefaultData()
    {
        $base_info = $this->_baseData();

        if (!$this->getQuote()->getBillingAddress()->getCountryId())
        {
            $result = $this->saveBilling(array(
                'country_id'        => $base_info['billing']['country_id'],
            	'region_id'         => $base_info['billing']['region_id'],
                'city'              => $base_info['billing']['city'],
                'postcode'          => $base_info['billing']['postcode'],
                'use_for_shipping'  => $base_info['equal'],
                'register_account'  => 0
            ), false, false);
        }

        if (!$this->getQuote()->getShippingAddress()->getCountryId()) {
            if (!$base_info['equal']) {
                $result = $this->saveShipping(array(
                    'country_id'        => $base_info['shipping']['country_id'],
                	'region_id'         => $base_info['shipping']['region_id'],
                    'city'              => $base_info['shipping']['city'],
                    'postcode'          => $base_info['shipping']['postcode']
                ), false, false);
            }
        }

        $this->getQuote()->collectTotals()->save();

        $this->usePayment();
        $this->useShipping();

        return $this;
    }

    private function _baseData()
    {
        $quote = $this->getQuote();
        // try to get shipping data from estimate dialog
        $ship_data	= $quote->getShippingAddress()->getData();

        $init_ship_data	= array(
			'country_id'   => !empty($ship_data['country_id'])?$ship_data['country_id']:null,
			'city'      => !empty($ship_data['city'])?$ship_data['city']:null,
			'region_id' => !empty($ship_data['region_id'])?$ship_data['region_id']:null,
			'postcode'  => !empty($ship_data['postcode'])?$ship_data['postcode']:null,
        );

        $init_bill_data = $init_ship_data;

        if(!empty($init_ship_data['region_id']) || !empty($init_ship_data['postcode']))
        {
	        $bill = $this->getQuote()->getBillingAddress()->getData();
	        if(!empty($bill['country_id']))
	        {
		        if(empty($bill['city']) && empty($bill['region_id']) && empty($bill['postcode']))
		        {
		        	$bill['country_id']	= $init_bill_data['country_id'];
		        	$bill['city']		= $init_bill_data['city'];
		        	$bill['region_id']	= $init_bill_data['region_id'];
		        	$bill['postcode']	= $init_bill_data['postcode'];
		        	if(!isset($bill['use_for_shipping']))
                		$bill['use_for_shipping'] = true;
                	if(!isset($bill['register_account']))
                		$bill['register_account'] = 0;

		        	$res = $this->saveBilling($bill, false, false);
		        }
	        }
        }

        $result = array(
            'shipping' => $init_ship_data,
            'billing' => $init_bill_data,
            'equal' => true
        );
        
        $customer	= Mage::getSingleton('customer/session')->getCustomer();
        $addresses	= $customer->getAddresses();
        
        if (!$customer || !$addresses)
        {
			$result['equal'] = true;

			// skip GeoIp search if user made 'Quote' on shopping cart page 
			if(empty($result['shipping']['country_id']) && empty($result['shipping']['postcode']))
			{
	            if (Mage::getStoreConfig('onepagecheckout/geo_ip/country'))
	            {
	                $geoip = geoip_open(Mage::getBaseDir('lib').DS.'MaxMind/GeoIP/data/'.Mage::getStoreConfig('onepagecheckout/geo_ip/country_file'),GEOIP_STANDARD);
	                $country_id	= geoip_country_code_by_addr($geoip, Mage::helper('core/http')->getRemoteAddr());
	                $result['shipping']['country_id'] = $country_id; 
	                $result['billing']['country_id'] = $country_id;
	                geoip_close($geoip);
	            }
	            
	            if (Mage::getStoreConfig('onepagecheckout/geo_ip/city'))
	            {
	                $geoip = geoip_open(Mage::getBaseDir('lib').DS.'MaxMind/GeoIP/data/'.Mage::getStoreConfig('onepagecheckout/geo_ip/city_file'),GEOIP_STANDARD);
	                $record = geoip_record_by_addr($geoip, Mage::helper('core/http')->getRemoteAddr());
	                if(!empty($record))
	                {
	                	if(isset($record->city) && !empty($record->city))
	                	{
		                	$result['shipping']['city']	= $record->city;
		                	$result['billing']['city'] = $record->city;
	                	}
	                	if(isset($record->postal_code) && !empty($record->postal_code))
	                	{
		                	$result['shipping']['postcode']	= $record->postal_code;
		                	$result['billing']['postcode']	= $record->postal_code;
	                	}
	                }
	                geoip_close($geoip);
	            }
			}

            if (empty($result['shipping']['country_id']))
            {
            	$country_id	= Mage::getStoreConfig('onepagecheckout/general/country');
                $result['shipping']['country_id'] = $country_id;
				$result['billing']['country_id'] = $country_id;
            }
        } 
        else 
        {
            $bill_addr = $customer->getPrimaryBillingAddress();
            if (!$bill_addr)
            {
                foreach ($addresses as $address) {
                    $bill_addr = $address;
                    break;
                }
            }
        	
        	$ship_addr = $customer->getPrimaryShippingAddress();
            if (!$ship_addr)
            {
                foreach ($addresses as $address)
                {
                    $ship_addr = $address;
                    break;
                }
            }

            $result['shipping']['country_id'] = $ship_addr->getCountryId();
            $result['billing']['country_id'] = $bill_addr->getCountryId();
            $eq = false;
            if($ship_addr->getId() === $bill_addr->getId())
            	$eq = true;
            $result['equal'] = $eq;
        }

        return $result;
    }

    public function initCheckout()
    {
        $checkout = $this->getCheckout();
        $cust_sess = $this->getCustomerSession();

        if ($this->getQuote()->getIsMultiShipping()) {
            $this->getQuote()->setIsMultiShipping(false);
            $this->getQuote()->save();
        }

        $customer = $cust_sess->getCustomer();
        if ($customer)
            $this->getQuote()->assignCustomer($customer);

        return $this;
    }
    
    public function usePayment($method_code = null)
    {
    	$store	= null;
    	if($this->getQuote())
    		$store = $this->getQuote()->getStoreId();

        $methods = Mage::helper('payment')->getStoreMethods($store, $this->getQuote());
        
        $payments = array();
        foreach ($methods as $method)
        {
            if ($this->_PaymentMethodAllowed($method))
                $payments[] = $method;
        }

        $cp = count($payments);
        if ($cp == 0)
        {
            $this->getQuote()->removePayment();
        }
        elseif ($cp == 1)
        {
            $payment = $this->getQuote()->getPayment();
            $payment->setMethod($payments[0]->getCode());
            $method = $payment->getMethodInstance();
            $method->assignData(array('method' => $payments[0]->getCode()));
        }
        else
        {
            $exist = false;
            if (!$method_code)
            {
                if ($this->getQuote()->isVirtual())
                    $method_code = $this->getQuote()->getBillingAddress()->getPaymentMethod();
                else
                    $method_code = $this->getQuote()->getShippingAddress()->getPaymentMethod();
            }
            
            if($method_code)
            {
                foreach ($payments as $payment)
                {
                    if ($method_code !== $payment->getCode())
                        continue;

                    $payment = $this->getQuote()->getPayment();
                    $payment->setMethod($method_code);
                    $method = $payment->getMethodInstance();
                    $method->assignData(array('method' => $method_code));
                    $exist = true;
                    break;
                }
            }
            if (!$method_code || !$exist)
            {
                $method_code = Mage::getStoreConfig('onepagecheckout/general/payment_method');
                foreach ($payments as $payment)
                {
                    if ($method_code !== $payment->getCode())
                        continue;

                    $payment = $this->getQuote()->getPayment();
                    $payment->setMethod($method_code);
                    $method = $payment->getMethodInstance();
                    $method->assignData(array('method' => $method_code));
                    $exist = true;
                    break;
                }
            }
            if (!$exist)
                 $this->getQuote()->removePayment();
        }

        return $this;
    }

    public function useShipping($method_code = null)
    {
        $rates = Mage::getModel('sales/quote_address_rate')->getCollection()->setAddressFilter($this->getQuote()->getShippingAddress()->getId())->toArray();

        $cr	= count($rates['items']);
        if (!$cr)
        {
            $this->getQuote()->getShippingAddress()->setShippingMethod(false);
        }
        elseif ($cr == 1)
        {
            $this->getQuote()->getShippingAddress()->setShippingMethod($rates['items'][0]['code']);
        }
        else
        {
            $exist = false;
            if (!$method_code)
                $method_code = $this->getQuote()->getShippingAddress()->getShippingMethod();

            if ($method_code)
            {
                foreach ($rates['items'] as $rate)
                {
                    if ($method_code === $rate['code'])
                    {
                        $this->getQuote()->getShippingAddress()->setShippingMethod($method_code);
                        $exist = true;
                        break;
                    }
                }
            }
            
            if (!$exist || !$method_code)
            {
                $method_code = Mage::getStoreConfig('onepagecheckout/general/shipping_method');
                foreach ($rates['items'] as $rate)
                {
                    if ($method_code === $rate['code'])
                    {
                        $this->getQuote()->getShippingAddress()->setShippingMethod($method_code);
                        $exist = true;
                        break;
                    }
                }
            }
            if (!$exist)
                $this->getQuote()->getShippingAddress()->setShippingMethod(false);
        }
        return $this;
    }

    public function getAddress($addr_id)
    {
        $address = Mage::getModel('customer/address')->load((int)$addr_id);
        $address->explodeStreetAddress();
        if ($address->getRegionId())
            $address->setRegion($address->getRegionId());
        return $address;
    }

    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn())
            return self::CUSTOMER;

        if (!$this->getQuote()->getCheckoutMethod())
        {
            if (Mage::helper('onepagecheckout')->isGuestCheckoutAllowed())
                $this->getQuote()->setCheckoutMethod(self::GUEST);
            else
                $this->getQuote()->setCheckoutMethod(self::REGISTER);
        }
        return $this->getQuote()->getCheckoutMethod();
    }

    public function saveCheckoutMethod($method)
    {
        if (empty($method))
            return array('error' => -1, 'message' => $this->_help_obj->__('Invalid data.'));

        $this->getQuote()->setCheckoutMethod($method)->save();
        return array();
    }

    public function saveShippingMethod($ship_method)
    {
        if (empty($ship_method))
            return array( 'message' => $this->_help_obj->__('Invalid shipping method.'), 'error' => -1);

        $rate = $this->getQuote()->getShippingAddress()->getShippingRateByCode($ship_method);
        if (!$rate)
            return array('message' => $this->_help_obj->__('Invalid shipping method.'), 'error' => -1);

        $this->getQuote()->getShippingAddress()->setShippingMethod($ship_method);
        $this->getQuote()->collectTotals()->save();
        return array();
    }

    public function saveBilling($data, $cust_addr_id, $validate = true, $skip_save = false)
    {
        if (empty($data))
            return array('error' => -1, 'message' => $this->_help_obj->__('Invalid data.'));

        $address = $this->getQuote()->getBillingAddress();
        if (!empty($cust_addr_id))
        {
            $cust_addr = Mage::getModel('customer/address')->load($cust_addr_id);
            if ($cust_addr->getId())
            {
                if ($cust_addr->getCustomerId() != $this->getQuote()->getCustomerId())
                    return array('error' => 1, 'message' => $this->_help_obj->__('Customer Address is not valid.'));

                $address->importCustomerAddress($cust_addr);
            }
        }
        else
        {
            unset($data['address_id']);
            $address->addData($data);
        }

        if($validate)
        {
        	$val_results = $this->validateAddress($address);
        	if ($val_results !== true)
            	return array('error' => 1, 'message' => $val_results);
        }

        if (isset($data['register_account']) && $data['register_account'])
            $this->getQuote()->setCheckoutMethod(self::REGISTER);
        else if ($this->getCustomerSession()->isLoggedIn())
            $this->getQuote()->setCheckoutMethod(self::CUSTOMER);
        else
            $this->getQuote()->setCheckoutMethod(self::GUEST);

		if($skip_save){
			
			$mage_ver = Mage::helper('onepagecheckout')->getMagentoVersion();
			if($mage_ver != '1.4.1.1' && $mage_ver != '1.4.1.0' && $mage_ver != '1.4.0.1' && $mage_ver != '1.4.0.0')
			{
	        	if (true !== ($result = $this->_validateCustomerData($data))) {
	            	return $result;
	        	}
			}
		}

		if($validate)
		{
        	if (!$this->getQuote()->getCustomerId() && (self::REGISTER == $this->getQuote()->getCheckoutMethod()))
        	{
            	if ($this->_customerEmailExists($address->getEmail(), Mage::app()->getWebsite()->getId()))
                	return array('error' => 1, 'message' => $this->_em_ex_msg);
        	}
        }

        $address->implodeStreetAddress();

        if (!$this->getQuote()->isVirtual())
        {
            $ufs = 0;
            if(isset($data['use_for_shipping']))
            	$ufs = (int) $data['use_for_shipping'];

            switch($ufs)
            {
                case 0:
                    $ship = $this->getQuote()->getShippingAddress();
                    $ship->setSameAsBilling(0);
                    break;
                case 1:
                    $bill = clone $address;
                    $bill->unsAddressId()->unsAddressType();
                    $ship = $this->getQuote()->getShippingAddress();
                    $ship_method = $ship->getShippingMethod();
                    $ship->addData($bill->getData());
                    $ship->setSameAsBilling(1)->setShippingMethod($ship_method)->setCollectShippingRates(true);
                    break;
            }
        }

        if ($validate){
        	$result = $this->_processValidateCustomer($address);
        	if($result !== true)
        		return $result;
        }

        // fixed by Alex Calko for saving data to define property shipping method
        if(!$skip_save)        
			$this->getQuote()->collectTotals()->save();

        return array();
    }
    
    public function saveShipping($data, $cust_addr_id, $validate = true, $skip_save = false)
    {
        if (empty($data))
            return array('message' => $this->_help_obj->__('Invalid data.'), 'error' => -1);

        $address = $this->getQuote()->getShippingAddress();

        if (empty($cust_addr_id))
        {
            unset($data['address_id']);
            $address->addData($data);
// fix (26.10.13) (when user change shipping address, system does not resave this parameter)
            $address->setSameAsBilling(0);        	
        }
        else
        {
            $cust_addr = Mage::getModel('customer/address')->load($cust_addr_id);
            if ($cust_addr->getId())
            {
                if ($this->getQuote()->getCustomerId() != $cust_addr->getCustomerId())
                    return array('message' => $this->_help_obj->__('Customer Address is not valid.'), 'error' => 1);

                $address->importCustomerAddress($cust_addr);
            }
        }
        
        $address->implodeStreetAddress();
        $address->setCollectShippingRates(true);

        if ($validate)
        {
        	$val_result = $this->validateAddress($address);
        	if($val_result !== true)
            	return array('message' => $val_result, 'error' => 1);
        }

        // fixed by Alex Calko for saving data to define property shipping method
        if(!$skip_save)        
			$this->getQuote()->collectTotals()->save();

        return array();
    }

    public function savePayment($data)
    {
        if (empty($data))
            return array('message' => $this->_help_obj->__('Invalid data.'), 'error' => -1);

        if (!$this->getQuote()->isVirtual())
        	$this->getQuote()->getShippingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        else
            $this->getQuote()->getBillingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);

        $payment = $this->getQuote()->getPayment();
        $payment->importData($data);

        $this->getQuote()->save();

        return array();
    }
   
    /**
     * Validate customer data and set some its data for further usage in quote
     * Will return either true or array with error messages
     *
     * @param array $data
     * @return true|array
     */
    protected function _validateCustomerData(array $data)
    {
        /** @var $customerForm Mage_Customer_Model_Form */
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('checkout_register')
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        $quote = $this->getQuote();
        if ($quote->getCustomerId()) {
            $customer = $quote->getCustomer();
            $customerForm->setEntity($customer);
            $customerData = $quote->getCustomer()->getData();
        } else {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer');
            $customerForm->setEntity($customer);
            $customerRequest = $customerForm->prepareRequest($data);
            $customerData = $customerForm->extractData($customerRequest);
        }

        $customerErrors = $customerForm->validateData($customerData);
        if ($customerErrors !== true) {
            return array(
                'error'     => -1,
                'message'   => implode(', ', $customerErrors)
            );
        }

        if ($quote->getCustomerId()) {
            return true;
        }

        $customerForm->compactData($customerData);

        if ($quote->getCheckoutMethod() == self::REGISTER) {
            // set customer password
            $customer->setPassword($customerRequest->getParam('customer_password'));
            $customer->setConfirmation($customerRequest->getParam('confirm_password'));
        } else {
            // spoof customer password for guest
            $password = $customer->generatePassword();
            $customer->setPassword($password);
            $customer->setConfirmation($password);
            // set NOT LOGGED IN group id explicitly,
            // otherwise copyFieldset('customer_account', 'to_quote') will fill it with default group id value
            $customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        }

        $result = $customer->validate();
        if (true !== $result && is_array($result)) {
            return array(
                'error'   => -1,
                'message' => implode(', ', $result)
            );
        }

        if ($quote->getCheckoutMethod() == self::REGISTER) {
            // save customer encrypted password in quote
            $quote->setPasswordHash($customer->encryptPassword($customer->getPassword()));
        }

        // copy customer/guest email to address
        $quote->getBillingAddress()->setEmail($customer->getEmail());

        // copy customer data to quote
        Mage::helper('core')->copyFieldset('customer_account', 'to_quote', $customer, $quote);

        return true;
    }

    protected function _processValidateCustomer(Mage_Sales_Model_Quote_Address $address)
    {
        if ($address->getGender())
            $this->getQuote()->setCustomerGender($address->getGender());
    	
        $dob = '';
        if ($address->getDob()) {
            $dob = Mage::app()->getLocale()->date($address->getDob(), null, null, false)->toString('yyyy-MM-dd');
            $this->getQuote()->setCustomerDob($dob);
        }

        if ($address->getTaxvat())
            $this->getQuote()->setCustomerTaxvat($address->getTaxvat());

        if ($this->getQuote()->getCheckoutMethod() == self::REGISTER)
        {
            $customer = Mage::getModel('customer/customer');
            $this->getQuote()->setPasswordHash($customer->encryptPassword($address->getCustomerPassword()));

            $cust_data	= array(
                'email'        => 'email',
                'password'     => 'customer_password',
                'confirmation' => 'confirm_password',            
                'firstname'    => 'firstname',
                'lastname'     => 'lastname',
                'gender'       => 'gender',
                'taxvat'       => 'taxvat');

            foreach ($cust_data as $key => $value)
                $customer->setData($key, $address->getData($value));

            if ($dob)
                $customer->setDob($dob);

            $val_result = $customer->validate();
            if ($val_result !== true && is_array($val_result))
                return array('message' => implode(', ', $val_result), 'error'   => -1);
        }
        elseif($this->getQuote()->getCheckoutMethod() == self::GUEST)
        {
            $email = $address->getData('email');
            if (!Zend_Validate::is($email, 'EmailAddress'))
                return array('message' => $this->_help_obj->__('Invalid email address "%s"', $email), 'error'   => -1);
        }

        return true;
    }
    
    public function validate()
    {
        $quote  = $this->getQuote();
        if ($quote->getIsMultiShipping())
            Mage::throwException($this->_help_obj->__('Invalid checkout type.'));

        if (!Mage::helper('onepagecheckout')->isGuestCheckoutAllowed() && $quote->getCheckoutMethod() == self::GUEST)
            Mage::throwException($this->_help_obj->__('Sorry, guest checkout is not allowed, please contact support.'));
    }

    public function saveOrder()
    {
		$info = Mage::getVersionInfo();
		$version	= "{$info['major']}.{$info['minor']}.{$info['revision']}.{$info['patch']}";

        $this->validate();
        $newCustomer = false;

        switch ($this->getCheckoutMethod())
        {
            case self::GUEST:
                $this->_prepareGuestQuote();
                break;
            case self::REGISTER:
                $this->_prepareNewCustomerQuote();
                $newCustomer = true;
                break;
            default:
                $this->_prepareCustomerQuote();
                break;
        }

        // mark that order will be saved by OPC module
        $this->getCheckout()->setProcessedOPC('opc');
        
        $service_quote = Mage::getModel('onepagecheckout/service_quote', $this->getQuote());
        if($version == '1.4.0.1' || $version == '1.4.0.0')
        	$order	= $service_quote->submit();
        else
        {
			$order	= $service_quote->submitAll();
			$order	= $service_quote->getOrder();
        }

        if ($newCustomer)
        {
            try {
                $this->_involveNewCustomer();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        
        if($version != '1.4.0.1' && $version != '1.4.0.0')
        {
	        $this->getCheckout()->setLastQuoteId($this->getQuote()->getId())
	            ->setLastSuccessQuoteId($this->getQuote()->getId())
	            ->clearHelperData();
        }
        
        if ($order)
        {        	
            Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order'=>$order, 'quote'=>$this->getQuote()));
            $r_url = $this->getQuote()->getPayment()->getOrderPlaceRedirectUrl();
            if(!$r_url)
            {
                try {
                    $order->sendNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

	        if($version == '1.4.0.1' || $version == '1.4.0.0')
	        {
				$this->getCheckout()->setLastQuoteId($this->getQuote()->getId())->setLastOrderId($order->getId())->setLastRealOrderId($order->getIncrementId())->setRedirectUrl($r_url)->setLastSuccessQuoteId($this->getQuote()->getId());
	        }
	        else
	        {
            	$this->getCheckout()->setLastOrderId($order->getId())->setRedirectUrl($r_url)->setLastRealOrderId($order->getIncrementId());

	            $agree = $order->getPayment()->getBillingAgreement();
	            if ($agree)
	                $this->getCheckout()->setLastBillingAgreementId($agree->getId());            	
            }
        }

		if($version != '1.4.0.1' && $version != '1.4.0.0')
		{
	        $profiles = $service_quote->getRecurringPaymentProfiles();
	        if ($profiles)
	        {
	            $ids = array();
	            foreach($profiles as $profile)
	                $ids[] = $profile->getId();
	
	            $this->getCheckout()->setLastRecurringProfileIds($ids);
	        }
	        
	        if($version != '1.4.1.0')
	        {
		        Mage::dispatchEvent(
		            'checkout_submit_all_after',
		            array('order' => $order, 'quote' => $this->getQuote(), 'recurring_profiles' => $profiles)
		        );
	        }
		}

        return $this;
    }

    protected function validateOrder()
    {
        if ($this->getQuote()->getIsMultiShipping())
            Mage::throwException($this->_help_obj->__('Invalid checkout type.'));

        if (!$this->getQuote()->isVirtual())
        {
            $address = $this->getQuote()->getShippingAddress();
            $addrVal = $this->validateAddress($address);
            if ($addrVal !== true)
                Mage::throwException($this->_help_obj->__('Please check shipping address.'));

            $method= $address->getShippingMethod();
            $rate  = $address->getShippingRateByCode($method);
            if (!$this->getQuote()->isVirtual() && (!$method || !$rate))
                Mage::throwException($this->_help_obj->__('Please specify a shipping method.'));
        }

        $addrVal = $this->validateAddress($this->getQuote()->getBillingAddress());
        if ($addrVal !== true)
            Mage::throwException($this->_help_obj->__('Please check billing address.'));

        if (!($this->getQuote()->getPayment()->getMethod()))
            Mage::throwException($this->_help_obj->__('Please select a valid payment method.'));
    }

    public function getLastOrderId()
    {
        $lo  = $this->getCheckout()->getLastOrderId();
        $order_id = false;
        if ($lo)
        {
            $order = Mage::getModel('sales/order');
            $order->load($lo);
            $order_id = $order->getIncrementId();
        }
        return $order_id;
    }
    
    public function validateAddress($address)
    {
        $errors = array();
        $helper = Mage::helper('customer');
        $address->implodeStreetAddress();
        $a_form = Mage::getStoreConfig('onepagecheckout/address_form');

        if (!Zend_Validate::is($address->getFirstname(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the first name.');

        if (!Zend_Validate::is($address->getLastname(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the last name.');

        if ($a_form['company'] === 'required' && !Zend_Validate::is($address->getCompany(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the company.');

        if ($a_form['state'] === 'required'  && $address->getCountryModel()->getRegionCollection()->getSize() && !Zend_Validate::is($address->getRegionId(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the state/province.');

        if ($a_form['address'] === 'required' && !Zend_Validate::is($address->getStreet(1), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the street.');

        if ($a_form['city'] === 'required' && !Zend_Validate::is($address->getCity(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the city.');

        $_opt_zip = Mage::helper('directory')->getCountriesWithOptionalZip();
        if ($a_form['zip'] === 'required'  && !in_array($address->getCountryId(), $_opt_zip) && !Zend_Validate::is($address->getPostcode(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the zip code.');

        if ($a_form['phone'] === 'required' && !Zend_Validate::is($address->getTelephone(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the phone number.');

        if ($a_form['fax'] === 'required' && !Zend_Validate::is($address->getFax(), 'NotEmpty'))
            $errors[] = $helper->__('Please enter the fax.');

        if ($a_form['country'] === 'required'  && !Zend_Validate::is($address->getCountryId(), 'NotEmpty'))
            $errors[] = $helper->__('Please choose the country.');

        if (empty($errors) || $address->getShouldIgnoreValidation())
            return true;

        return $errors;
    }
        
    protected function _prepareNewCustomerQuote()
    {
        $bill = $this->getQuote()->getBillingAddress();
        $ship = null;
        if(!$this->getQuote()->isVirtual())
        	$ship = $this->getQuote()->getShippingAddress();

        $customer = $this->getQuote()->getCustomer();
        $cust_bill = $bill->exportCustomerAddress();
        $customer->addAddress($cust_bill);
        $bill->setCustomerAddress($cust_bill);
        $cust_bill->setIsDefaultBilling(true);
        if($ship)
        {
        	if(!$ship->getSameAsBilling())
        	{
	            $cust_ship = $ship->exportCustomerAddress();
	            $customer->addAddress($cust_ship);
	            $ship->setCustomerAddress($cust_ship);
	            $cust_ship->setIsDefaultShipping(true);
        	}
        	else
				$cust_bill->setIsDefaultShipping(true);
        }

        if (!$bill->getCustomerGender() && $this->getQuote()->getCustomerGender())
            $bill->setCustomerGender($this->getQuote()->getCustomerGender());
        
        if (!$bill->getCustomerDob() && $this->getQuote()->getCustomerDob())
            $bill->setCustomerDob($this->getQuote()->getCustomerDob());

        if (!$bill->getCustomerTaxvat() && $this->getQuote()->getCustomerTaxvat())
            $bill->setCustomerTaxvat($this->getQuote()->getCustomerTaxvat());

        Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $bill, $customer);

        $customer->setPassword($customer->decryptPassword($this->getQuote()->getPasswordHash()));
        $customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
        $this->getQuote()->setCustomer($customer)->setCustomerId(true);
    }

    protected function _prepareCustomerQuote()
    {
        $bill	= $this->getQuote()->getBillingAddress();
        $ship = null;
        if(!$this->getQuote()->isVirtual())
        	$ship = $this->getQuote()->getShippingAddress();
        
        $customer = $this->getCustomerSession()->getCustomer();
        if (!$bill->getCustomerId() || $bill->getSaveInAddressBook())
        {
            $cust_bill = $bill->exportCustomerAddress();
            $customer->addAddress($cust_bill);
            $bill->setCustomerAddress($cust_bill);
        }
        if ($ship && (($ship->getSaveInAddressBook() && !$ship->getSameAsBilling()) || (!$ship->getSameAsBilling() && !$ship->getCustomerId())))
        {
            $cust_ship = $ship->exportCustomerAddress();
            $customer->addAddress($cust_ship);
            $ship->setCustomerAddress($cust_ship);
        }

        if (isset($cust_bill) && !$customer->getDefaultBilling())
            $cust_bill->setIsDefaultBilling(true);

        if ($ship && isset($cust_ship) && !$customer->getDefaultShipping())
            $cust_ship->setIsDefaultShipping(true);
        elseif (isset($cust_bill) && !$customer->getDefaultShipping())
            $cust_bill->setIsDefaultShipping(true);

        $this->getQuote()->setCustomer($customer);
    }

    protected function _prepareGuestQuote()
    {
        $quote = $this->getQuote();
        $quote->setCustomerId(null)->setCustomerEmail($quote->getBillingAddress()->getEmail())->setCustomerIsGuest(true)->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        return $this;
    }
    
    protected function _involveNewCustomer()
    {
        $customer = $this->getQuote()->getCustomer();
        if ($customer->isConfirmationRequired())
        {
            $customer->sendNewAccountEmail('confirmation', '', $this->getQuote()->getStoreId());
            $url = Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
            $this->getCustomerSession()->addSuccess(Mage::helper('onepagecheckout')->__('Account confirmation is required. Please, check your email for confirmation link. <a href="%s">Click here</a> to resend confirmation email.', $url));
        }
        else
        {
        	$customer->sendNewAccountEmail('registered', '', $this->getQuote()->getStoreId());
            $this->getCustomerSession()->loginById($customer->getId());
        }
        return $this;
    }
    
    protected function _customerEmailExists($email, $web_id = null)
    {
        $customer = Mage::getModel('customer/customer');
        if ($web_id)
            $customer->setWebsiteId($web_id);

        $customer->loadByEmail($email);
        if ($customer->getId())
            return $customer;

        return false;
    }
    
    public function setVerificationLib($lib)
    {
    	$this->verification_lib	= $lib;
    }

    public function getVerificationLib()
    {
    	return $this->verification_lib;
    }

    public function validate_address($type = 'Billing', $data = false)
    {  
    	// for testing 
    	/*
    	$candidates	= array();
    	
		$us_states	= array();
		$states = Mage::getModel('directory/country')->load('US')->getRegions(); 
		foreach ($states as $state)
			$us_states[$state->getCode()] = $state->getId();
    	
		$add['street'] = 'my test address';
		$add['city']	= 'test city';
		$add['region_abbr']	= 'CA';
		$add['region']	= $us_states[$add['region_abbr']];
		$add['postcode']	= '12345';
		
		$candidates[] = $add;
		$candidates[] = $add;
		$candidates[] = $add;
		$candidates[] = $add;
		$candidates[] = $add;
			
   		$test_data	= array('error' => 'not valid');

    	$test_data['candidates']	= $candidates;
    	
    	$this->getCheckout()->{"set{$type}ValidationResults"}($test_data);
    	
    	return $test_data;
    	*/
    	// end for testing

    	$lib	= $this->getVerificationLib();
    	if($lib == 'ups')
    		$results = $this->ups_validate_street_address($type, $data);
    	elseif($lib == 'usps')
    		$results = $this->usps_validate_street_address($type, $data);
    	else
    		$results = false;

    	$this->getCheckout()->{"set{$type}ValidationResults"}($results);
    	
    	return $results;
    }
    
    // UPS api    
	public function ups_validate_street_address($type = 'Billing', $data = false)
	{
		$error	= false;

		if(!in_array($type, array('Billing', 'Shipping')) && !$data)
			return false;

		if(!$data)
		{
        	$address = $this->getQuote()->{"get{$type}Address"}();
        	$data	= $address->getData();
		}

        if(isset($data['country_id']) && !empty($data['country_id']) && $data['country_id'] == 'US')
        {
        	// skip regions
        	$state_no_ups	= array('hawaii', 'virgin islands', 'puerto rico','guam');
        	if(!empty($data['region']))
        	{
        		$reg = strtolower($data['region']);
        		if(in_array($reg, $state_no_ups))
        			return false;
        	}
        	
        	if(!empty($data['street']) && !empty($data['city']) && !empty($data['postcode']) && !empty($data['region_id']))
        	{
				$regionModel = Mage::getModel('directory/region')->load($data['region_id']); 
				$regionId = $regionModel->getCode();

				if(empty($regionId))
					return false;

				$test_mode	= (bool)Mage::getStoreConfig('onepagecheckout/ups_address_verification/test_mode');
				$login	= Mage::getStoreConfig('onepagecheckout/ups_address_verification/ups_login');
				$pass	= Mage::getStoreConfig('onepagecheckout/ups_address_verification/ups_pass');
				$key	= Mage::getStoreConfig('onepagecheckout/ups_address_verification/ups_access_key');

				/// setup config
				$GLOBALS ['ups_api'] ['access_key'] = $key;
				$GLOBALS ['ups_api'] ['developer_key'] = '';

				if($test_mode)
				{
					$GLOBALS ['ups_api'] ['server'] = 'https://wwwcie.ups.com';
					$GLOBALS ['ups_street_level_api'] ['server']	= 'https://wwwcie.ups.com';
					// in other DOCS test server should be  https://wwwcie.ups.com/webservices/XAV
				}
				else
				{
					$GLOBALS ['ups_api'] ['server'] = 'https://www.ups.com';
					$GLOBALS ['ups_street_level_api'] ['server']	= 'https://onlinetools.ups.com';
					// in other DOCS live server should be  https://onlinetools.ups.com/webservices/XAV
				}

				/** set the username and password used to connect to UPS **/
				$GLOBALS ['ups_api'] ['username'] = $login;
				$GLOBALS ['ups_api'] ['password'] = $pass;
				///////////
				
				include_once('iwd/opcvalidation/ups/UpsAPI.php');
				include_once('iwd/opcvalidation/ups/UpsAPI/USStreetLevelValidation.php');

				$check_address = array(
					'street' => $data['street'],
				    'city' => $data['city'],
				    'state' => $regionId,
				    'zip_code' => $data['postcode'],
					'country' => 'US',
				); // end address

				$customer_data = '';

				$validation = new UpsAPI_USStreetLevelValidation($check_address);
				$xml = $validation->buildRequest($customer_data);

				// returns an array
				$response = $validation->sendRequest($xml, false);

				if(isset($response['Response']))
				{
					$match_type = $validation->getMatchType();
					if($match_type == 'Unknown')
					{
						$error	= 'NO';
					}
					
					// get lis of addresses
					$candidates = $this->get_ups_candidates($response);
					
					return array('error' => $error, 'candidates' => $candidates, 'original_address' => $check_address);
				}
        	}
        }
        else
        	return false;
		
		return $error;
	}
	
	public function get_ups_candidates($response)
	{
		$valid_addresses	= array();
		
		$us_states	= array();
		$states = Mage::getModel('directory/country')->load('US')->getRegions(); 
		foreach ($states as $state)
			$us_states[$state->getCode()] = $state->getId();

		if(isset($response['AddressKeyFormat']))
		{
			$addresses_array = $response['AddressKeyFormat'];
			if(isset($addresses_array['AddressClassification']))
			{
				$valid_candidate = $this->parse_ups_candidate($addresses_array);
				if(!empty($valid_candidate))
				{
					$valid_candidate['region'] = $us_states[$valid_candidate['region_abbr']];
					$valid_addresses[] = $valid_candidate;
				}
			}
			else // we have list of addresses
			{
				foreach($addresses_array as $candidate)
				{
					$valid_candidate = $this->parse_ups_candidate($candidate);
					if(!empty($valid_candidate))
					{
						$valid_candidate['region'] = $us_states[$valid_candidate['region_abbr']];
						$valid_addresses[] = $valid_candidate;
					}
				}
			}
		}

		return $valid_addresses;
	}
	
	public function parse_ups_candidate($candidate)
	{
		if($candidate['AddressClassification']['Code'] == 0)
			return false;

		$add = array();
		if(!isset($candidate['AddressLine']))
			return false;
			
		if(is_array($candidate['AddressLine']))
			$add['street'] = $candidate['AddressLine'][0];
		else
			$add['street'] = $candidate['AddressLine'];
			
		if(!isset($candidate['PoliticalDivision2']))
			return false;
			
		$add['city']	= $candidate['PoliticalDivision2'];
					
		if(!isset($candidate['PoliticalDivision1']))
			return false;

		$add['region_abbr']	= strtoupper($candidate['PoliticalDivision1']);
		
		if(!isset($candidate['PostcodePrimaryLow']))
			return false;

		$add['postcode']	= $candidate['PostcodePrimaryLow'];
		if(isset($candidate['PostcodeExtendedLow']) && !empty($candidate['PostcodeExtendedLow']))
			$add['postcode'].= '-'.$candidate['PostcodeExtendedLow'];

		return $add;
	}
	// End UPS api
	
    // USPS api
	public function usps_validate_street_address($type = 'Billing', $data = false)
	{
		$error	= false;

		if(!in_array($type, array('Billing', 'Shipping')) && !$data)
			return false;

		if(!$data)
		{
        	$address = $this->getQuote()->{"get{$type}Address"}();
        	$data	= $address->getData();
		}

        if(isset($data['country_id']) && !empty($data['country_id']) && $data['country_id'] == 'US')
        {
        	// skip regions
        	$state_no_ups	= array('hawaii', 'virgin islands', 'puerto rico','guam');
        	if(!empty($data['region']))
        	{
        		$reg = strtolower($data['region']);
        		if(in_array($reg, $state_no_ups))
        			return false;
        	}
        	
        	if(!empty($data['street']) && !empty($data['city']) && !empty($data['postcode']) && !empty($data['region_id']))
        	{
				$regionModel = Mage::getModel('directory/region')->load($data['region_id']); 
				$regionId = $regionModel->getCode();

				if(empty($regionId))
					return false;

				$test_mode	= (bool)Mage::getStoreConfig('onepagecheckout/usps_address_verification/test_mode');
				$key	= Mage::getStoreConfig('onepagecheckout/usps_address_verification/usps_access_key');

				if(empty($key))
					return false;

				$check_address = array(
					'street' => $data['street'],
				    'city' => $data['city'],
				    'state' => $regionId,
				    'zip_code' => $data['postcode'],
					'country' => 'US',
				); // end address

				include_once('iwd/opcvalidation/usps/USPSAddressVerify.php');
					
				$verify = new USPSAddressVerify($key);
				
				if($test_mode)
					$verify->setTestMode(true);
				else
					$verify->setTestMode(false);
				
				$usps_address = new USPSAddress;
				
				if(isset($data['company']) && !empty($data['company']))
					$usps_address->setFirmName($data['company']);
				
				$street_info	= $address->getStreet();
				$street1	= '';
				$street2	= '';
				if(is_array($street_info))
				{
					$street1	= $street_info[0];
					if(isset($street_info[1]))
						$street2	= $street_info[1];
				}
				else
					$street1	= $data['street'];

				$usps_address->setApt($street2);
				$usps_address->setAddress($street1);
				$usps_address->setCity($data['city']);
				$usps_address->setState($regionId);
				
				$zip	= trim($data['postcode']);
				$zip	= str_replace(' ','-',$zip);
				$z_p	= explode('-',$zip);
				
				$zip4	= '';
				$zip5	= $z_p[0];
				if(isset($z_p[1]) && !empty($z_p[1]))
					$zip4	= $z_p[1];
				
				$usps_address->setZip5($zip5);
				$usps_address->setZip4($zip4);
 
				$verify->addAddress($usps_address);

				// Perform the request and return result
				$verify->verify();
				$response	= $verify->getArrayResponse();

				if($verify->isSuccess())
				{
					// get lis of addresses
					$candidates = $this->get_usps_candidates($response);
					// check if candidate address is differ from entered
					if(empty($candidates))
						return array('error' => 'NO', 'candidates' => array(), 'original_address' => $check_address);

					if(strtolower($candidates[0]['street']) != strtolower($street1)
					|| strtolower($candidates[0]['city']) != strtolower($check_address['city'])
					|| strtolower($candidates[0]['region_abbr']) != strtolower($check_address['state'])
					|| strtolower($candidates[0]['postcode']) != strtolower($check_address['zip_code']))
					{
						$error	= 'YES';
					}
					
					return array('error' => $error, 'candidates' => $candidates, 'original_address' => $check_address);
				}
				else
				{
					$er_code = $verify->getErrorCode();
					if($er_code == '-2147219401')
					{
						return array('error' => 'NO', 'candidates' => array(), 'original_address' => $check_address);
					}
					elseif($er_code == '80040b1a')
					{
						return array('error' => 'API Authorization failure. User is not authorized to use API Verify.', 'candidates' => array(), 'original_address' => $check_address);
//						
					}
					elseif($er_code == '-2147219040')
					{
						return array('error' => 'This Information has not been included in this Test Server.', 'candidates' => array(), 'original_address' => $check_address);
					}
				}
        	}
        }
        else
        	return false;
		
		return $error;
	}
	
	public function get_usps_candidates($response)
	{
		$valid_addresses	= array();
		
		$us_states	= array();
		$states = Mage::getModel('directory/country')->load('US')->getRegions(); 
		foreach ($states as $state)
			$us_states[$state->getCode()] = $state->getId();

		if(isset($response['AddressValidateResponse']))
		{
			if(isset($response['AddressValidateResponse']['Address']))
			{
				$valid_candidate = $this->parse_usps_candidate($response['AddressValidateResponse']['Address']);
				if(!empty($valid_candidate))
				{
					$valid_candidate['region'] = $us_states[$valid_candidate['region_abbr']];
					$valid_addresses[] = $valid_candidate;
				}
			}
		}

		return $valid_addresses;
	}
	
	public function parse_usps_candidate($candidate)
	{
		if(!isset($candidate['Address2']) || empty($candidate['Address2']))
			return false;

		$add = array();
		$add['street'] = $candidate['Address2'];
			
		if(!isset($candidate['City']) || empty($candidate['City']))
			return false;

		$add['city']	= $candidate['City'];
					
		if(!isset($candidate['State']) || empty($candidate['State']))
			return false;

		$add['region_abbr']	= strtoupper($candidate['State']);
		
		if(!isset($candidate['Zip5']) || empty($candidate['Zip5']))
			return false;

		$add['postcode']	= $candidate['Zip5'];
		if(isset($candidate['Zip4']) && !empty($candidate['Zip4']))
			$add['postcode'].= '-'.$candidate['Zip4'];

		return $add;
	}
	// End USPS api
}