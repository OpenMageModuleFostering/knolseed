<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Used in creating options for Yes|No config value selection
 *
 */
class Knolseed_Engage_Model_Productvalues
{ 

    /**
     * Options getter
     *
     * @return array
     */
  public function toOptionArray()
  {
    Mage::log("Entry Knolseed_Engage_Model_Productvalues::toOptionArray()",null,'knolseed.log');
	  
    $entityTypeId = Mage::getModel('eav/entity')
                  ->setType('catalog_product')
                  ->getTypeId();
    $attributesInfo = Mage::getResourceModel('eav/entity_attribute_collection')
                    ->setEntityTypeFilter($entityTypeId)  //4 = product entities
                    ->addSetInfo()
                    ->getData();

    $categoryattribute = false ;
    foreach ($attributesInfo as $attr){ 
      
      if ($attr['frontend_label'] != "" || $attr['attribute_code'] == 'category_ids' ){
        $array[] = array('value' => $attr['attribute_code'], 'label' => $attr['frontend_label']);  
      }
  
      if( $attr['attribute_code'] == 'category_ids' )
         $categoryattribute = true ;
    }

    if( $categoryattribute == false )
      $array[] = array('value' => "category_ids", 'label' => "category_ids");


    return $array; 
	  
    /*return array(
      array('value' => 0, 'label' => Mage::helper('engage')->__('First item')),
      array('value' => 1, 'label' => Mage::helper('engage')->__('Second item')),
      array('value' => 2, 'label' => Mage::helper('engage')->__('third item')),
     // and so on...
    );*/
  }
    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            0 => Mage::helper('adminhtml')->__('No'),
            1 => Mage::helper('adminhtml')->__('Yes'),
        );
    }

}
