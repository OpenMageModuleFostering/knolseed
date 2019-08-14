<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Adminhtml_EngageController extends Mage_Adminhtml_Controller_action
{
  #public $kf_authurl = 'http://117.218.62.90/settings/token.json?';
  public $kf_authurl = 'http://app.knolseed.com/settings/token.json?';


  /**
   * Check AWS connection by calling GetToken API
   *
   * Token will be saved in DB on successful authentication
   * @return  bool
   */
  public function TestAws_ConnectionAction() {
    Mage::log("Entry Knolseed_Engage_Adminhtml_EngageController::TestAws_ConnectionAction()",null,'knolseed.log');    

    $email = $this->getRequest()->getParam('email');
    $password = $this->getRequest()->getParam('password');

    try {
      $http = new Varien_Http_Adapter_Curl();
      $config = array('timeout' => 30); # Or whatever you like!
      $config['header'] = false;

      $requestQuery = "email=".$email."&password=".$password;

      $http->setConfig($config);

      ## make a POST call
      $http->write(Zend_Http_Client::GET, $this->kf_authurl . $requestQuery );

      ## Get Response
      $response = $http->read();
      $data = json_decode($response);

      # Close Call
      $http->close(); 

      if( $data->success && $data->data->authentication_token ) {
        Mage::log('KS Response:'.print_r($data->data,true), null, 'knolseed.log');
        $token = trim($data->data->authentication_token);
        $bucket = trim($data->data->s3_bucket);
        $folder = trim($data->data->s3_folder);

        $coreConfigObj = new Mage_Core_Model_Config();
        $coreConfigObj->saveConfig('engage_options/aws/token', $token, 'default', 0);
        echo 1;
        return true;

      } else {
        // Log error message
        Mage::log('Knolseed get token - Invalid username/password.',null,'knolseed.err');
        Mage::log('Knolseed get token - Invalid username/password.',null,'knolseed.log');
        echo 0;
        return false;
      }
                
    } catch (Exception $e) {
      // Log error message
      Mage::log('Knolseed get token - Error while getting token from Knolseed API.',null,'knolseed.err');
      Mage::log('Knolseed get token - Error while getting token from Knolseed API.',null,'knolseed.log');
      echo 0;
      return false;
    }

  }




  /**
   * Export Product Attributes CSV file for download
   *
   * 
   */
  public function Product_CSVAction()
  {
    Mage::log("Entry Knolseed_Engage_Adminhtml_EngageController::Product_CSVAction()",null,'knolseed.log');    

    $product_csv_time = Mage::getStoreConfig('engage_options/product/cron_time');
    $path = Mage::getBaseDir(); # if you want to add new folder for csv files just add name of folder in dir with single quote.

    $product_enable_flag = Mage::getStoreConfig('engage_options/product/status');

    if($product_enable_flag == 1){
      $productvalues = Mage::getModel('engage/productvalues')->toOptionArray();

      $new_pro_array = array();

      foreach ($productvalues AS $key => $value) {
        $new_pro_array[] = $value['value'];
      }

      $productvaluescount = count($new_pro_array);

        //$product_Attr_str = implode(",",$new_pro_array);
      $product_Attr_str = implode(",",array_map('trim',$new_pro_array));

      $fp = fopen($path."/Product_Attributes_".date("m-d-y-g-i-a").".csv", 'x+') or die(Mage::log("file opening error!!"));

      $headers = "Sku,".str_replace("sku,",'',$product_Attr_str)."\n";
      fwrite($fp,$headers);

      $from = date('Y-m-d H:i:s', mktime(date("H"), date("i"), date("s"), date("m"), date("d")-1, date("Y")) ) ; //date('Y-m-d', strtotime("-2 day")); 
      $to = date('Y-m-d H:i:s');

      $collection = Mage::getModel('catalog/product')->getCollection();
      $collection->addAttributeToFilter('updated_at', array('gt' =>$from));
      $collection->addAttributeToFilter('updated_at', array('lt' => $to));
      $attribute_values = "";
      foreach ($collection as $products) //loop for getting products
      {                   
        $product = Mage::getModel('catalog/product')->load($products->getId()); 
        $attributes = $product->getAttributes();
        
        $attribute_values .= '"'.$product->getSku().'",';
        for($i=0;$i<$productvaluescount;$i++)
        {
          $attributeName = $new_pro_array[$i];
          if ($attributeName!='sku'){
            $attributeValue = null;     
            if(array_key_exists($attributeName , $attributes)){
              $attributesobj = $attributes["{$attributeName}"];
              $attributeValue = $attributesobj->getFrontend()->getValue($product);
            }
            $string=str_replace('"','\"',$attributeValue);
            $attribute_values .= '"'.$string.'",';
          }
          
        }
        $attribute_values .= "\n";
      }
      fwrite($fp,$attribute_values);
      fclose($fp); 

      $filepath = $path."/Product_Attributes_".date("m-d-y-g-i-a").".csv";
      $this->getResponse ()
      ->setHttpResponseCode ( 200 )
      ->setHeader ( 'Pragma', 'public', true )
      ->setHeader ( 'Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true )
      ->setHeader ( 'Content-type', 'application/force-download' )
      ->setHeader ( 'Content-Length', filesize($filepath) )
      ->setHeader ('Content-Disposition', '' . '; filename=' . basename($filepath) );
      $this->getResponse ()->clearBody ();
      $this->getResponse ()->sendHeaders ();
      readfile ( $filepath );

    }else{
      $url = Mage::helper("adminhtml")->getUrl("adminhtml/system_config/edit/section/engage_options/");
      header("Location: ".$url);
    }

  }



  /**
   * Export Customer Attributes CSV file for download
   *
   * 
   */
  public function Customer_CSVAction()
  {
    Mage::log("Entry Knolseed_Engage_Adminhtml_EngageController::Customer_CSVAction()",null,'knolseed.log');    

    ob_start();

    $customer_csv_time = Mage::getStoreConfig('engage_options/customer/cron_time');

    $path = Mage::getBaseDir(); # if you want to add new folder for csv files just add name of folder in dir with single quote.
    $customer_enable_flag = Mage::getStoreConfig('engage_options/customer/status');
    if($customer_enable_flag == 1){
      $customervalues = Mage::getModel('engage/customervalues')->toOptionArray();

      $new_cust_array = array();

      foreach ($customervalues AS $key => $value) {
        $new_cust_array[] = $value['value'];
      }

      $customervaluescount = count($new_cust_array);

      $fp = fopen($path."/Customer_Attributes_".date("m-d-y-g-i-a").".csv", 'x+') or die(Mage::log("file opening error!!"));

      $headers = "";
      $headers = " Customer Id ,".implode(",", array_map('trim',$new_cust_array))."\n";
      fwrite($fp,$headers);
      $model = Mage::getModel('customer/customer'); //getting product model
      $collection = $model->getCollection(); //products collection
      $attribute_values = "";

      foreach ($collection as $customers) //loop for getting products
      {                   
        $customer = Mage::getModel('customer/customer')->load($customers->getId()); 
        $attributes = $customer->getAttributes();
        
        $attribute_values .= '"'.$customer->getId().'",';
        
        foreach( $new_cust_array as $key => $vals )
        {           
          $attributeValue = $customer->getData( $vals ) ;
          
          if( $vals == "default_billing" || $vals == "default_shipping" ) {
            
            $address = Mage::getModel('customer/address')->load($attributeValue);
            $htmlAddress = $address->format('html');
            $string = (string)$htmlAddress;
            $string = ereg_replace("[ \t\n\r]+", "", $string);
            $string= str_replace(array('<br/>', ',', '<br />'), ' ', $string);
            $attribute_values .='"'.str_replace('"','\"',$string).'",';

          } else {

            $string = ereg_replace("[ \t\n\r]+", "", $attributeValue);

            $string=str_replace('"','\"',$string);
            $attribute_values .= '"'.str_replace(array('<br/>', ',', '<br />'), ' ', $string).'",';
          }
          
        }
        $attribute_values .= "\n";
      }
        
      fwrite($fp,$attribute_values);
      fclose($fp); 

      $filepath = $path."/Customer_Attributes_".date("m-d-y-g-i-a").".csv";
      $this->getResponse ()
      ->setHttpResponseCode ( 200 )
      ->setHeader ( 'Pragma', 'public', true )
      ->setHeader ( 'Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true )
      ->setHeader ( 'Content-type', 'application/force-download' )
      ->setHeader ( 'Content-Length', filesize($filepath) )
      ->setHeader ('Content-Disposition', '' . '; filename=' . basename($filepath) );
      $this->getResponse ()->clearBody ();
      $this->getResponse ()->sendHeaders ();
      readfile ( $filepath );
      #unlink($filepath);
    }else{
      $url = Mage::helper("adminhtml")->getUrl("adminhtml/system_config/edit/section/engage_options/");
      header("Location: ".$url);
      ob_end_flush();
    }  

  }

}
