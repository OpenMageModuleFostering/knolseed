<?php 
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Buttonexport extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        Mage::log("Entry Knolseed_Engage_Block_Buttonexport::_getElementHtml()",null,'knolseed.log');
        $this->setElement($element);
        //$url = $this->getUrl('catalog/product'); //
        $url = $this->getUrl('engage/adminhtml_engage/Customer_CSV');
        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('scalable')
                    ->setLabel('Export')
                    ->setOnClick("setLocation('$url')")
                    ->toHtml();

        return $html;
    }
}
?>
