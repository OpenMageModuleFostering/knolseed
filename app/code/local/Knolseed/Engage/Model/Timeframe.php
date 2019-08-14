<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Timeframe extends Varien_Object
{
    const STATUS_ENABLED  = 1;
    const STATUS_DISABLED = 2;

    static public function getOptionArray()
    {
        return array(
            self::STATUS_ENABLED    => Mage::helper('engage')->__('Yes'),
            self::STATUS_DISABLED   => Mage::helper('engage')->__('No')
        );
    }
    
    
    static public function toOptionArray()
    {
        

        return array(
            array('value' => '3', 'label' => '3 Months'),
            array('value' => '6', 'label' => '6 Months'),
            array('value' => '12', 'label' => '12 Months'),
            array('value' => '24', 'label' => '24 Months'),
            
            
        );  
    }
}
