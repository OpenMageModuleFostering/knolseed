<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }

    protected function _getPageTrackingCode($accountId)
    {
        Mage::log("Entry Knolseed_Engage_Block_Ga::_getPageTrackingCode()",null,'knolseed.log');

        $pageName   = trim($this->getPageName());
        $optPageURL = '';
        if ($pageName && preg_match('/^\/.*/i', $pageName)) {
            $optPageURL = ", '{$this->jsQuoteEscape($pageName)}'";
        }
        /* Start Google Javascript code   */
        if(Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $customer_id = $customerData->getId();
            $script = Mage::getStoreConfig('engage_options/google/google_content');
             $script=str_replace("customer_id",$customer_id,$script);
        }
        else
        {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $customer_id = $customerData->getId();
            $script = Mage::getStoreConfig('engage_options/google/google_content');
             $script=str_replace('__bc.push(["_setCustomerId", "customer_id"])','',$script);
        }
        /* End Google Javascript code   */
        /*if($script)
        {*/
            $finalscript=$script."_gaq.push(['_setAccount', '{$this->jsQuoteEscape($accountId)}']);
        _gaq.push(['_trackPageview'{$optPageURL}]);
        ";
        return $finalscript;
        /*}*/
        /*else
        {
            return "_gaq.push(['_setAccount', '{$this->jsQuoteEscape($accountId)}']);
        _gaq.push(['_trackPageview'{$optPageURL}]);
        ";

        }*/
    }
    
    public function getGa()     
    { 
        Mage::log("Entry Knolseed_Engage_Block_Ga::_getGa()",null,'knolseed.log');
        if (!$this->hasData('ga')) {
            $this->setData('ga', Mage::registry('ga'));
        }
        return $this->getData('ga');
        
    }


    protected function _getOrdersTrackingCode()
    {
        Mage::log("Entry Knolseed_Engage_Block_Ga::_getOrdersTrackingCode()",null,'knolseed.log');

        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('entity_id', array('in' => $orderIds))
        ;
        $result = array();
        foreach ($collection as $order) {
            if ($order->getIsVirtual()) {
                $address = $order->getBillingAddress();
            } else {
                $address = $order->getShippingAddress();
            }
            $result[] = sprintf("_gaq.push(['_addTrans', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);",
                $order->getIncrementId(),
                $this->jsQuoteEscape(Mage::app()->getStore()->getFrontendName()),
                $order->getBaseGrandTotal(),
                $order->getBaseTaxAmount(),
                $order->getBaseShippingAmount(),
                $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($address->getCity())),
                $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($address->getRegion())),
                $this->jsQuoteEscape(Mage::helper('core')->escapeHtml($address->getCountry()))
            );
            foreach ($order->getAllVisibleItems() as $item) {
                $cats = $item->getProduct()->getCategoryIds();
                $catname = "";
                foreach ($cats as $category_id) {
                        $_cat = Mage::getModel('catalog/category')->load($category_id) ;
                        $catname = $_cat->getName();
                }
                $result[] = sprintf("_gaq.push(['_addItem', '%s', '%s', '%s', '%s', '%s', '%s']);",
                    $order->getIncrementId(),
                    $this->jsQuoteEscape($item->getSku()), $this->jsQuoteEscape($item->getName()),
                    $catname, // there is no "category" defined for the order item
                    $item->getBasePrice(), $item->getQtyOrdered()
                );
            }
            $result[] = "_gaq.push(['_trackTrans']);";
        }
        return implode("\n", $result);
    }
}
