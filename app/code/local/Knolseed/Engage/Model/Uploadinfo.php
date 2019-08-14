<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Uploadinfo extends Varien_Object
{    
    static public function toOptionArray()
    {

        return array(
            array('value' => '1', 'label' => 'Transactions')            
        );  
    }
}
