<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Time extends Varien_Object
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
            array('value' => '01:00', 'label' => '01:00 AM'),
            
            array('value' => '02:00', 'label' => '02:00 AM'),
            
            array('value' => '03:00', 'label' => '03:00 AM'),
            
            array('value' => '04:00', 'label' => '04:00 AM'),
            
            array('value' => '05:00', 'label' => '05:00 AM'),
            
            array('value' => '06:00', 'label' => '06:00 AM'),
            
            array('value' => '07:00', 'label' => '07:00 AM'),
            
            array('value' => '08:00', 'label' => '08:00 AM'),
            
            array('value' => '09:00', 'label' => '09:00 AM'),
            
            array('value' => '10:00', 'label' => '10:00 AM'),
            
            array('value' => '11:00', 'label' => '11:00 AM'),
            
            array('value' => '12:00', 'label' => '12:00 PM'),
            
            array('value' => '13:00', 'label' => '13:00 PM'),
            
            array('value' => '14:00', 'label' => '14:00 PM'),
            
            array('value' => '15:00', 'label' => '15:00 PM'),
            
            array('value' => '16:00', 'label' => '16:00 PM'),
            
            array('value' => '17:00', 'label' => '17:00 PM'),
            
            array('value' => '18:00', 'label' => '18:00 PM'),
            
            array('value' => '19:00', 'label' => '19:00 PM'),
            
            array('value' => '20:00', 'label' => '20:00 PM'),
            
            array('value' => '21:00', 'label' => '21:00 PM'),
            
            array('value' => '22:00', 'label' => '22:00 PM'),
            
            array('value' => '23:00', 'label' => '23:00 PM'),

            array('value' => '00:00', 'label' => '00:00 AM'),
            
        );  
    }
}
