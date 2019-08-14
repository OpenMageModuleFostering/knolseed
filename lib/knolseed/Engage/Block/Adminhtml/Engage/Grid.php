<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Adminhtml_Engage_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Grid::__construct()",null,'knolseed.log');
      $this->setId('engageGrid');
      $this->setDefaultSort('engage_id');
      $this->setDefaultDir('ASC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Grid::_prepareCollection()",null,'knolseed.log');
      $collection = Mage::getModel('engage/engage')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Grid::_prepareColumns()",null,'knolseed.log');

      $this->addColumn('engage_id', array(
          'header'    => Mage::helper('engage')->__('ID'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'engage_id',
      ));

      $this->addColumn('title', array(
          'header'    => Mage::helper('engage')->__('Title'),
          'align'     =>'left',
          'index'     => 'title',
      ));

	  /*
      $this->addColumn('content', array(
			'header'    => Mage::helper('engage')->__('Item Content'),
			'width'     => '150px',
			'index'     => 'content',
      ));
	  */

      $this->addColumn('status', array(
          'header'    => Mage::helper('engage')->__('Status'),
          'align'     => 'left',
          'width'     => '80px',
          'index'     => 'status',
          'type'      => 'options',
          'options'   => array(
              1 => 'Enabled',
              2 => 'Disabled',
          ),
      ));
	  
        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('engage')->__('Action'),
                'width'     => '100',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('engage')->__('Edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
        ));
		
		$this->addExportType('*/*/exportCsv', Mage::helper('engage')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('engage')->__('XML'));
	  
    return parent::_prepareColumns();
  }


  protected function _prepareMassaction()
  {
      Mage::log("Entry Knolseed_Engage_Block_Adminhtml_Engage_Grid::_prepareMassaction()",null,'knolseed.log');
      $this->setMassactionIdField('engage_id');
      $this->getMassactionBlock()->setFormFieldName('engage');

      $this->getMassactionBlock()->addItem('delete', array(
           'label'    => Mage::helper('engage')->__('Delete'),
           'url'      => $this->getUrl('*/*/massDelete'),
           'confirm'  => Mage::helper('engage')->__('Are you sure?')
      ));

      $statuses = Mage::getSingleton('engage/status')->getOptionArray();

      array_unshift($statuses, array('label'=>'', 'value'=>''));
      $this->getMassactionBlock()->addItem('status', array(
           'label'=> Mage::helper('engage')->__('Change status'),
           'url'  => $this->getUrl('*/*/massStatus', array('_current'=>true)),
           'additional' => array(
                  'visibility' => array(
                       'name' => 'status',
                       'type' => 'select',
                       'class' => 'required-entry',
                       'label' => Mage::helper('engage')->__('Status'),
                       'values' => $statuses
                   )
           )
      ));
      return $this;
  }

  public function getRowUrl($row)
  {
      return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }

}
