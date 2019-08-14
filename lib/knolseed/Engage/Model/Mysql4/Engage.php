<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Mysql4_Engage extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        // Note that the engage_id refers to the key field in your database table.
        $this->_init('engage/engage', 'process_id');
    }
}
