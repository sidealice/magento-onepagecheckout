<?php

class IWD_OnepageCheckout_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_agree = null;

    public function isOnepageCheckoutEnabled()
    {
        return (bool)Mage::getStoreConfig('onepagecheckout/general/enabled');
    }

    public function isGuestCheckoutAllowed()
    {
        return Mage::getStoreConfig('onepagecheckout/general/guest_checkout');
    }

    public function isShippingAddressAllowed()
    {
    	return Mage::getStoreConfig('onepagecheckout/general/shipping_address');
    }

    public function getAgreeIds()
    {
        if (is_null($this->_agree))
        {
            if (Mage::getStoreConfigFlag('onepagecheckout/agreements/enabled'))
            {
                $this->_agree = Mage::getModel('checkout/agreement')->getCollection()
                    												->addStoreFilter(Mage::app()->getStore()->getId())
                    												->addFieldToFilter('is_active', 1)
                    												->getAllIds();
            }
            else
            	$this->_agree = array();
        }
        return $this->_agree;
    }
    
    public function isSubscribeNewAllowed()
    {
        if (!Mage::getStoreConfig('onepagecheckout/general/newsletter_checkbox'))
            return false;

        $cust_sess = Mage::getSingleton('customer/session');
        if (!$cust_sess->isLoggedIn() && !Mage::getStoreConfig('newsletter/subscription/allow_guest_subscribe'))
            return false;

		$subscribed	= $this->getIsSubscribed();
		if($subscribed)
			return false;
		else
			return true;
    }
    
    public function getIsSubscribed()
    {
        $cust_sess = Mage::getSingleton('customer/session');
        if (!$cust_sess->isLoggedIn())
            return false;

        return Mage::getModel('newsletter/subscriber')->getCollection()
            										->useOnlySubscribed()
            										->addStoreFilter(Mage::app()->getStore()->getId())
            										->addFieldToFilter('subscriber_email', $cust_sess->getCustomer()->getEmail())
            										->getAllIds();
    }
    
    public function getOPCVersion()
    {
    	return (string) Mage::getConfig()->getNode()->modules->IWD_OnepageCheckout->version;
    }
    
	public function isMageEnterprise(){
		return Mage::getConfig()->getModuleConfig('Enterprise_Enterprise') && Mage::getConfig()->getModuleConfig('Enterprise_AdminGws') && Mage::getConfig()->getModuleConfig('Enterprise_Checkout') && Mage::getConfig()->getModuleConfig('Enterprise_Customer');
	}

    public function getMagentoVersion()
    {
		$ver_info = Mage::getVersionInfo();
		$mag_version	= "{$ver_info['major']}.{$ver_info['minor']}.{$ver_info['revision']}.{$ver_info['patch']}";
		
		return $mag_version;
    }  

    public function getCurStoreUrl()
    {
		$storeId	= Mage::app()->getStore()->getId();
		return Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }

    /*
     * PREDEFINED for OPC Signature module, which use this function in review.phtml template
     */
    public function getSUrl()
    {
    	return '';
    }
    
    public function isAddressVerificationEnabled()
    {
    	if($this->isUPSEnabled()) // ups is primary verification
    		return 'ups';
    	if($this->isUSPSEnabled())
    		return 'usps';
    	return false;
    }
    
    public function getEnabledVerification()
    {
    	return $this->isAddressVerificationEnabled();
    }

    public function isUPSEnabled()
    {
    	return (bool)Mage::getStoreConfig('onepagecheckout/ups_address_verification/enabled');
    }
    public function isUSPSEnabled()
    {
    	return (bool)Mage::getStoreConfig('onepagecheckout/usps_address_verification/enabled');
    }
    
    public function allowNotValidAddress($library = false)
    {
    	if(!$library)
    		$library	= $this->getEnabledVerification();
    	switch($library)
    	{
    		case 'ups':
    			return (bool)Mage::getStoreConfig('onepagecheckout/ups_address_verification/allow_not_valid_address');
    			break;
    		case 'usps':
    			return (bool)Mage::getStoreConfig('onepagecheckout/usps_address_verification/allow_not_valid_address');
    			break;
    		default:
    			return false;
    			break;
    	}
    }

	function isMobile()
	{
		$mobiles = array('foma','softbank','android','kddi','dopod','helio','hosin','huawei','coolpad',
		'webos','techfaith','ktouch','nexian','wellcom','bunjalloo','maui','mmp','wap','phone','iemobile',
		'longcos','pantech','gionee','portalmmm','haier','mobileexplorer','palmsource',
		'palmscape','motorola','nokia','palm','iphone','ipad','ipod','sony','ericsson','blackberry',
		'cocoon','blazer','lg','amoi','xda','mda','vario','htc','samsung','sharp','sie-','alcatel','benq',
		'ipaq','mot-','playstation portable','hiptop','nec-','panasonic','philips','sagem','sanyo',
		'spv','zte','sendo','symbian','symbianos','elaine','palm','series60','windows ce','obigo',
		'netfront','openwave','mobilexplorer','operamini','opera mini','digital paths','avantgo',
		'xiino','novarra','vodafone','docomo','o2','mobile','wireless','j2me','midp','cldc',
		'up.link','up.browser','smartphone','cellphone');

		$agent	= strtolower($_SERVER['HTTP_USER_AGENT']);
		foreach ($mobiles as $device)
		{
			if (FALSE !== (strpos($agent, $device)))
				return TRUE;
		}
		
		return FALSE;    
	}
    
}