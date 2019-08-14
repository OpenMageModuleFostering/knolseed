<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Adminhtml_Engage extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
  	Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage::__construct()",null,'knolseed.log');
    $this->_controller = 'adminhtml_engage';
    $this->_blockGroup = 'engage';
    $this->_headerText = Mage::helper('engage')->__('Item Manager');
    $this->_addButtonLabel = Mage::helper('engage')->__('Add Item');
    parent::__construct();
  }
}
