<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
    	
    	/*
    	 * Load an object by id 
    	 * Request looking like:
    	 * http://site.com/setattribute?id=15 
    	 *  or
    	 * http://site.com/setattribute/id/15 	
    	 */
    	/* 
		$setattribute_id = $this->getRequest()->getParam('id');

  		if($setattribute_id != null && $setattribute_id != '')	{
			$setattribute = Mage::getModel('setattribute/setattribute')->load($setattribute_id)->getData();
		} else {
			$setattribute = null;
		}	
		*/
		
		 /*
    	 * If no param we load a the last created item
    	 */ 
    	/*
    	if($setattribute == null) {
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$setattributeTable = $resource->getTableName('setattribute');
			
			$select = $read->select()
			   ->from($setattributeTable,array('setattribute_id','title','content','status'))
			   ->where('status',1)
			   ->order('created_time DESC') ;
			   
			$setattribute = $read->fetchRow($select);
		}
		Mage::register('setattribute', $setattribute);
		*/

			
		$this->loadLayout();     
		$this->renderLayout();
    }
    
}
