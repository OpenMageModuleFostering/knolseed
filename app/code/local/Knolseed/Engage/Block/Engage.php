<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Engage extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
    
     public function getEngage()     
     { 
        Mage::log("Entry Knolseed_Engage_Block_Engage::getEngage()",null,'knolseed.log');
        if (!$this->hasData('engage')) {
            $this->setData('engage', Mage::registry('engage'));
        }
        return $this->getData('engage');
        
    }
}
