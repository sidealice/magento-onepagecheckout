<?php
class IWD_OnepageCheckout_Block_Addresscandidates extends Mage_Core_Block_Template
{
    public function getBillingValidationResults()
    {
    	$checkout	= Mage::getSingleton('onepagecheckout/type_geo');
    	return $checkout->getCheckout()->getBillingValidationResults();
/*    	
        if (!$this->hasAgreements())
        {
        	$agre = array();
            if (Mage::getStoreConfigFlag('onepagecheckout/agreements/enabled'))
            {
                $agre = Mage::getModel('checkout/agreement')->getCollection()
                    										->addStoreFilter(Mage::app()->getStore()->getId())
                    										->addFieldToFilter('is_active', 1);
                
            }
			$this->setAgreements($agre);            
        }
        return $this->getData('agreements');
*/
    }

    public function getShippingValidationResults()
    {
    	$checkout	= Mage::getSingleton('onepagecheckout/type_geo');
    	return $checkout->getCheckout()->getShippingValidationResults();
    }    
}
