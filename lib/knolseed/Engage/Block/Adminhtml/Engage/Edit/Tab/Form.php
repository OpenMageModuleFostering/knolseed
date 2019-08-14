<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Adminhtml_Engage_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{

  protected function _prepareForm()
  {
      Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Edit_Tab_Form::_prepareForm()",null,'knolseed.log');
      $form = new Varien_Data_Form();
      $this->setForm($form);
      $fieldset = $form->addFieldset('engage_form', array('legend'=>Mage::helper('engage')->__('Item information')));
     
      $fieldset->addField('title', 'text', array(
          'label'     => Mage::helper('engage')->__('Title'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'title',
      ));

      $fieldset->addField('filename', 'file', array(
          'label'     => Mage::helper('engage')->__('File'),
          'required'  => false,
          'name'      => 'filename',
	  ));
		
      $fieldset->addField('status', 'select', array(
          'label'     => Mage::helper('engage')->__('Status'),
          'name'      => 'status',
          'values'    => array(
              array(
                  'value'     => 1,
                  'label'     => Mage::helper('engage')->__('Enabled'),
              ),

              array(
                  'value'     => 2,
                  'label'     => Mage::helper('engage')->__('Disabled'),
              ),
          ),
      ));
     
      $fieldset->addField('content', 'editor', array(
          'name'      => 'content',
          'label'     => Mage::helper('engage')->__('Content'),
          'title'     => Mage::helper('engage')->__('Content'),
          'style'     => 'width:700px; height:500px;',
          'wysiwyg'   => false,
          'required'  => true,
      ));
     
      if ( Mage::getSingleton('adminhtml/session')->getEngageData() )
      {
          $form->setValues(Mage::getSingleton('adminhtml/session')->getEngageData());
          Mage::getSingleton('adminhtml/session')->setEngageData(null);
      } elseif ( Mage::registry('engage_data') ) {
          $form->setValues(Mage::registry('engage_data')->getData());
      }
      return parent::_prepareForm();
  }

}
