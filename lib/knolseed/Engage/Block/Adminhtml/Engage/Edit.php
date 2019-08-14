<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Adminhtml_Engage_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
        Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Edit::__construct()",null,'knolseed.log');                 
        $this->_objectId = 'id';
        $this->_blockGroup = 'engage';
        $this->_controller = 'adminhtml_engage';
        
        $this->_updateButton('save', 'label', Mage::helper('engage')->__('Save Item'));
        $this->_updateButton('delete', 'label', Mage::helper('engage')->__('Delete Item'));
		
        $this->_addButton('saveandcontinue', array(
            'label'     => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);

        $this->_formScripts[] = "
            function toggleEditor() {
                if (tinyMCE.getInstanceById('engage_content') == null) {
                    tinyMCE.execCommand('mceAddControl', false, 'engage_content');
                } else {
                    tinyMCE.execCommand('mceRemoveControl', false, 'engage_content');
                }
            }

            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    public function getHeaderText()
    {
        Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Edit::getHeaderText()",null,'knolseed.log');
        if( Mage::registry('engage_data') && Mage::registry('engage_data')->getId() ) {
            return Mage::helper('engage')->__("Edit Item '%s'", $this->htmlEscape(Mage::registry('engage_data')->getTitle()));
        } else {
            return Mage::helper('engage')->__('Add Item');
        }
    }
    
}
