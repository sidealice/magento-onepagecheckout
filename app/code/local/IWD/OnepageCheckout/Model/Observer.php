<?php
class IWD_OnepageCheckout_Model_Observer
{
    public function addHistoryComment($data)
    {
        $comment	= Mage::getSingleton('customer/session')->getOrderCustomerComment();
        $comment	= trim($comment); 
        if (!empty($comment))
			$data['order']->addStatusHistoryComment($comment)->setIsVisibleOnFront(true)->setIsCustomerNotified(false);
    }

    public function removeHistoryComment()
    {
        Mage::getSingleton('customer/session')->setOrderCustomerComment(null);
    }

    public function emptyCart()
    {
		if (Mage::helper('onepagecheckout')->isOnepageCheckoutEnabled())
		{
			$sess = Mage::getSingleton('checkout/session');
			// check if order has been processed by OPC module
			$processedOPC	= $sess->getProcessedOPC();
			if($processedOPC == 'opc')
			{
				$sess->setProcessedOPC('');
				$cartHelper = Mage::helper('checkout/cart');
				$items = $cartHelper->getCart()->getItems();
				foreach ($items as $item) {
					$itemId = $item->getItemId();
					$cartHelper->getCart()->removeItem($itemId)->save();
				}
				
				$sess->clear();
			}
		}
    }
}
