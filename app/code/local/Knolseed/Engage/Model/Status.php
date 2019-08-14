<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Status extends Varien_Object
{
    const STATUS_ENABLED	= 1;
    const STATUS_DISABLED	= 2;

    static public function getOptionArray()
    {
        return array(
            self::STATUS_ENABLED    => Mage::helper('engage')->__('Enabled'),
            self::STATUS_DISABLED   => Mage::helper('engage')->__('Disabled')
        );
    }
    
    
    static public function toOptionArray()
    {
        return array(
            self::STATUS_ENABLED    => Mage::helper('engage')->__('Yes'),
            self::STATUS_DISABLED   => Mage::helper('engage')->__('No')
        );
    }
}
