<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Helper_Data extends Mage_Core_Helper_Abstract
{

  public $daytimeinminutes = '1440';
  public $numberofdays = '30';
  public $interval = '240';
  public $records = '500';
  public $customerattributes = array();
  public $productattributes = array();
  public $categoryAttributes = array();
  public $intervalRange = 30;


  public function __construct() {
    Mage::log("Entry Knolseed_Engage_Helper_Data::__construct()",null,'knolseed.log');

    // Get Customer Attributes from Database
    $customervalues = Mage::getModel('engage/customervalues')->toOptionArray();     
    foreach ($customervalues AS $key => $value) 
    {
      $this->customerattributes[] = $value['value'];
    }

    // Get Product Attributes from Database
    $productvalues = Mage::getModel('engage/productvalues')->toOptionArray();
    foreach ($productvalues AS $key => $value) 
    {
      $this->productattributes[] = $value['value'];
    }

    Mage::log("productattributes=".$this->productattributes, null, 'knolseed.log');    
  }


  /**
   * This method checks next execution time for upload items CSV file.
   * 
   */
  public function startUploadData(){
    Mage::log("Entry Knolseed_Engage_Helper_Data::startUploadData()",null,'knolseed.log');

    try{
      $uploadtime = Mage::getStoreConfig('upload_options/upload/transaction');
      
      // return false if there is no time set.
      if( !trim($uploadtime) ) {
        return false ;
      }

      $time = Mage::getStoreConfig('upload_options/upload/time');
      $uploadinfo = explode(",",Mage::getStoreConfig('upload_options/upload/upload_info',Mage::app()->getStore()->getId())); 
      
      //if($uploadtime == Date("Y-m-d H:i"))
      if( $this->checkExecuteTime($uploadtime) )
      {
        // set time backtime
        $coredataobj = new Mage_Core_Model_Config();
        $coredataobj->saveConfig('upload_options/upload/transaction', 'default', 0);

        $timeinterval = Mage::getStoreConfig('upload_options/upload/timeframe');
        $nextexecutiontime = date('Y-m-d', strtotime($timeinterval." month"));

        // Get total number of days
        $totalnoofdays = $this->numberofdays*$timeinterval;

        if(in_array('1', $uploadinfo))
        { 
          for($i=$totalnoofdays;$i>=0;$i--){
            $this->getTransactionInfo($i,$uploadtime, $type='transaction',$timeinterval);
          }
        }

        if(in_array('2', $uploadinfo))
        {
          for($k=$totalnoofdays;$k>=0;$k--){
            $this->getBrowsingInfo($k,$uploadtime,$type='weblog',$timeinterval);
          }
        }

        /*$coreConfigObj3 = new Mage_Core_Model_Config();
        $coreConfigObj3->saveConfig('upload_options/upload/transaction', $nextexecutiontime." ".$time, 'default', 0);*/

      }
    }catch(Exception $e){
      $errormessage = "Critical Error! Transaction data sync unable to authenticate with Knolseed. Please email support@knolseed.com about this error, and we will help you to fix this problem." ;

      $this->errorAdminNotification('Upload-initialize-error','uploaddata',$errormessage,'',true);
    }

  }


  public function printCategories(){
    Mage::log("Entry Knolseed_Engage_Helper_Data::printCategories()",null,'knolseed.log');
    # $this->printCategories_1();
  }


  public function printCategories_1(){
    $category = Mage::getModel('catalog/category');
    $tree = $category->getTreeModel();
    $tree->load();
    $ids = $tree->getCollection()->getAllIds();
    if ($ids){
      foreach ($ids as $id){
        $cat = Mage::getModel('catalog/category');
        $cat->load($id);

        $entity_id = $cat->getId();
        $name = $cat->getName();
        $url_key = $cat->getUrlKey();
        $url_path = $cat->getUrlPath();
        $level = $cat->getLevel();


        # $parent_id = $cat->getParentId();
        # Mage::log("Category: ID=".$entity_id.", Name=".$name.", URL=".$url_path.", ParentId=".$parent_id,null,'knolseed.log');

        Mage::log("Category: ID=".$entity_id.", Name=".$name.", URL=".$url_path, null, 'knolseed.log');
        if($level && $level==2){
          Mage::log("Its a base category", null, 'knolseed.log');
        }
      }
    }else{
      Mage::log("Categories is empty!",null,'knolseed.log');
    }
  }


  /**
   * This method returns information related to sales order item.
   * 
   */ 
  public function getTransactionInfo($count,$startendtime,$type,$duration){
    Mage::log("Entry Knolseed_Engage_Helper_Data::getTransactionInfo()",null,'knolseed.log');

    try{
      // Get sales order itmes collection
      $collection = Mage::getResourceModel('sales/order_item_collection');

      $createdday = date('Y-m-d',strtotime("-$count day"));
      $filerandate = date('Ymd',strtotime("-$count day"));
      $fileranstartdate = date('Y-m-d 00:00:00',strtotime("-$count day"));
      $fileranenddate = date('Y-m-d 24:00:00',strtotime("-$count day"));

      $collection->getSelect()->joinLeft(array('sfo'=>'sales_flat_order'),
        'main_table.order_id = sfo.entity_id',array('sfo.shipping_amount','sfo.customer_id','sfo.increment_id',
          'sfo.grand_total'));

      $collection->addFieldToFilter('main_table.created_at',array('like' => $createdday.'%'));
      
      // File path for saving CSV
      $path =  Mage::getBaseDir('var')."/";

      $dateTs = strtotime($createdday);
      $dateStr = date('Ymd',$dateTs);
      // CSV filename
      $filename = "Txn_".$dateStr."_1_of_1.csv.gz" ;

      // Open CSv file in gzip mode
      //$fp = fopen($path."/".$filename, 'w');
      $fp = gzopen($path.$filename,'w9');

      // Metadata headers for CSV file
      // Line items 1 as per requirement
      $removedays = $duration*30 ; 
      $currentday = date('Ymd');
      //$startdaytime = date('Y-m-d H:i:s', strtotime(-$removedays." day"));
      $startdaytime = date('Y-m-d H:i:s');
      $etime = strtotime($startendtime);
      $endtime = date('Y-m-d H:i:s', $etime);
      $metaheaders = "";
      $metaheaders = '# "'.$type.'","'.$filerandate.'","'.$fileranstartdate.'","'.$fileranenddate.'"'."\n" ;
      //$metaheaders = "# ".$type.",".$currentday.",".$startdaytime.",".$endtime."\n" ;
      gzwrite($fp, $metaheaders);

      // Line items 2 as per requirement
      $metaheadersline2 = "";
      $metaheadersline2 = '# id=\'Transaction_id\', cid=\'Customer_id\', sku=\'Product_id\', category=\'Category\', timestamp=\'Timestamp\', total=\'Transaction Amount\''."\n" ;
      gzwrite($fp, $metaheadersline2);

      //Double quotes logic for headers
      $transaction_id_header = 'Transaction_id';
      $customer_id_header = 'Customer_id';
      $timestamp_header = 'Timestamp';
      $Product_id_header = 'Product_id';
      $Category_header = 'Category';
      $Tax_header = 'Tax';
      $Shipping_header = 'Shipping';

      //$handle = fopen('test.csv', 'w');
      //fputcsv($fp, array($transaction_id_header,$customer_id_header,$timestamp_header,$Product_id_header,$Category_header,
                //'Transaction Amount','No of items',$Tax_header,$Shipping_header,'Product Description'));
      $headers_transaction = '"'.$transaction_id_header.'","'.$customer_id_header.'","'.$timestamp_header.'","'.$Product_id_header.'","'.$Category_header.'","Transaction Amount","No of items","'.$Tax_header.'","'.$Shipping_header.'","Product Description"'."\n" ;
      fwrite($fp, $headers_transaction) ;

      // Iterate through sales order items
      foreach($collection as $col){
        $customer_id = str_replace('"', '""', $col['customer_id']);
        $transaction_id = str_replace('"', '""', $col['increment_id']);
        $timestamp = $col['created_at'];
        $product_id = str_replace('"', '""', $col['product_id']);
        $transaction_amount = $col['grand_total'];
        $items = $col['qty_ordered'];
        $tax = $col['tax_amount'];
        $shipping = $col['shipping_amount'];
        $description = str_replace('"', '""', $col['description']);
        $proid = str_replace('"', '""', $col['product_id']);
        $product = Mage::getModel('catalog/product')->load($proid);
        $product_price = $product->getFinalPrice() ;
        //$cats = implode(",", $product->getCategoryIds());
        
        $categorynames = array() ;
        foreach( $product->getCategoryIds() as $categoryid ) {
          $catagory_model = Mage::getModel('catalog/category');
          $categories = $catagory_model->load($categoryid); // where $id will be the known category id 
          $categorynames[] = $categories->getName();
        }
        $cats = implode(",", $categorynames);
        $cats = str_replace('"', '""', $cats);

        // Puting sales order items into CSV file
        //fputcsv($fp, array($transaction_id,$customer_id,$timestamp,$product_id,$cats,$transaction_amount,
                  // $items,$tax,$shipping,$description));

        fwrite($fp, '"'.$transaction_id.'","'.$customer_id.'","'.$timestamp.'","'.$product_id.'","'.$cats.'","'.$product_price.'","'.$items.'","'.$tax.'","'.$shipping.'","'.$description.'"'."\n");
        
      }
      // Close file
      //fclose($fp);
      gzclose($fp);

      // Upload Transaction attributes CSV to S3 bucket
      $filepath = $path."/".$filename;
      $actual_file_name = $filename ;

      // push file to S3 bucket
      $observer = new Knolseed_Engage_Model_Observer();

      // unlink file if successfully pushed to S3 bucket
      if( $observer->pushFileToS3($filepath,$actual_file_name,$type='transaction','') ) {
        unlink($filepath);
      }

    }catch(Exception $e){
      $errormessage = "Critical Error!  Transaction data dump failed for ". $createdday ." with error ". $filename ." Please email support@knolseed.com about this error, and we will help you to fix this problem." ;

        // Error notification for exception
      $this->errorAdminNotification('transactionCSV','transaction',$errormessage,$filename,true);
    }
  }


  /**
   * This method returns information related to visitor's browsing information.
   * 
   */ 
  public function getBrowsingInfo($count,$startendtime,$type,$duration){
    Mage::log("Entry Knolseed_Engage_Helper_Data::getBrowsingInfo()",null,'knolseed.log');

    try{
      // Get read connection object
      $readAdapter = Mage::getSingleton('core/resource')->getConnection('core_read');
      
      // Get date to filter collection
      $createdday = date('Y-m-d',strtotime("-$count day"));
      $filerandate = date('Ymd',strtotime("-$count day"));
      $fileranstartdate = date('Y-m-d 00:00:00',strtotime("-$count day"));
      $fileranenddate = date('Y-m-d 24:00:00',strtotime("-$count day"));

      // Select query for retrieving required browsing info
      $select = $readAdapter->select()
      ->from('log_url', array('visitor_id','visit_time'))
      ->join(array('lui' => 'log_url_info'), 'lui.url_id=log_url.url_id', array('url'))
      ->where('visit_time like ?', $createdday.'%');

      // Browsing info collection
      $data = $readAdapter->fetchAll($select);

      // Path to CSV file
      $path =  Mage::getBaseDir('var')."/";

      $dateTs = strtotime($createdday);
      $dateStr = date('Ymd',$dateTs);
      // CSV filename
      $filename = "Browsing_".$dateStr."_1_of_1.csv.gz" ;

      // CSV Filename
      # $filename = "Browsing_".$createdday.".csv.gz" ;
      
      //$fp = fopen($path."/".$filename, 'x+');
      $fp = gzopen($path.$filename,'w9');
      //$handle = fopen('test.csv', 'w');

      // Metadata headers for CSV file
      // Line items 1 as per requirement
      $removedays = $duration*30 ; 
      $currentday = $filerandate;
      $startdaytime = date('Y-m-d H:i:s', strtotime(-$removedays." day"));
      $etime = strtotime($startendtime);
      $endtime = date('Y-m-d H:i:s', $etime);
      $metaheaders = "";
      //$metaheaders = "# ".$type.",".$currentday.",".$startdaytime.",".$endtime."\n" ;
      $metaheaders = '# '.$type.'","'.$currentday.'","'.$fileranstartdate.'","'.$fileranenddate.'"'."\n" ;
      gzwrite($fp, $metaheaders);

      // Line items 2 as per requirement 
      $metaheadersline2 = "";
      $metaheadersline2 = '# cid=\'Customer_id\', url=\'URL\', timestamp=\'Timestamp\''."\n" ;
      //$metaheadersline2 = "# cid='Customer_id', url='URL', timestamp='Timestamp'\n" ;
      gzwrite($fp, $metaheadersline2);

      $customer_id_header = 'Customer_id';
      $timestamp_header = 'Timestamp';
      $URL_header = 'URL';
      $Product_id_header = 'Product_id';
      $Category_header = 'Category';

      $headers_transaction = '"'.$customer_id_header.'","'.$timestamp_header.'","'.$URL_header.'","'.$Product_id_header.'","'.$Category_header.'"'."\n" ;
      //fputcsv($fp, array('Customer_id','Timestamp','URL','Product_id','Category'));
      fwrite($fp, $headers_transaction) ;

      foreach($data as $urlinfo){
        $product = strpos($urlinfo['url'], 'product');

        if ($product !== false){
          $url = explode("/", $urlinfo['url']);
          $productid = str_replace('"', '""', $url[count($url)-1]);

          $product = Mage::getModel('catalog/product')->load($productid);               
          $categorynames = array() ;
          foreach( $product->getCategoryIds() as $categoryid ) {
            $catagory_model = Mage::getModel('catalog/category');
            $categories = $catagory_model->load($categoryid); // where $id will be the known category id 
            $categorynames[] = $categories->getName();
          }
          $categoryname = str_replace('"', '""', implode(",", $categorynames) );

          /*$catagory_model = Mage::getModel('catalog/category');
          $categories = $catagory_model->load($categoryid); // where $id will be the known category id 
          $categoryname = $categories->getName();*/
          # $stringurl = urlencode($urlinfo['url'])."\n";
          $stringurl = $urlinfo['url']."\n";
          $stringurl = str_replace('"', '""', $stringurl);
        }

        if( !is_numeric($productid)) {
          $productid = $categoryname = "" ;
        }

        // Puting sales browsing items into CSV file
        //fputcsv($fp, array($urlinfo['visitor_id'],$urlinfo['visit_time'],$stringurl,$productid,$categoryname)); 
        fwrite($fp, '"'.str_replace('"', '""', $urlinfo['visitor_id']).'","'.str_replace('"', '""', $urlinfo['visit_time']).'","'.$stringurl.'","'.$productid.'","'.$categoryname.'"'."\n");  
      }

      // Close file
      // fclose($fp);
      gzclose($fp);
      // Upload Browsing attributes CSV to S3 bucket
      $filepath = $path."/".$filename;
      $actual_file_name = $filename;

      // push file to S3 bucket
      $observer = new Knolseed_Engage_Model_Observer();

      // unlink file if successfully pushed to S3 bucket
      if( $observer->pushFileToS3($filepath,$actual_file_name,$type='browsing','') ) {
        unlink($filepath);
      }

    }catch(Exception $e){
      $errormessage = "Critical Error!  Transaction data dump failed for ". $createdday ." with error ". $filename ." Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      
      // Error notification for exception
      $this->errorAdminNotification('browsingCSV','browsing',$errormessage,$filename,true);
    }   
  }


  /**
   * Check if its time to run!
   * FIXME: Convert all dates to epoch timestamps and then compare
   */
  public function checkExecuteTime($runTime) {
    Mage::log("Entry Knolseed_Engage_Helper_Data::checkExecuteTime()",null,'knolseed.log');

    list($date, $time) = explode(" ", $runTime) ;

    $min = date("i");
    $hr = date("H");
    $aHr = date("H") + 1;

    if( !trim($time) ) {
      if($min > $this->intervalRange){       
        $from = $hr.':'.($this->intervalRange+1);
        $to   = $aHr.':00';
      }else{
        $from = $hr.':'.'00';
        $to   = $hr.':'.$this->intervalRange;
      }
    }else {
      if($min > $this->intervalRange){       
        $from = date("Y-m-d")." ".$hr.':'.($this->intervalRange+1);
        $to   = date("Y-m-d")." ".$aHr.':00';
      }else{
        $from = date("Y-m-d")." ".$hr.':'.'00';
        $to   = date("Y-m-d")." ".$hr.':'.$this->intervalRange;
      }
    }

    $cRuntime = strtotime($runTime);
    $cFrom = strtotime($from);
    $cTo = strtotime($to);

    if(($cRuntime >= $cFrom)&& ($cRuntime <= $cTo)){
      return true;
    }else{
      return false;       
    }

  }


  /**
   * Create Customer Attributes CSV file
   *
   * Place created CSV file to AWS S3 bucket
   * 
   */
  public function processCustomerfile(){
    Mage::log("Entry Knolseed_Engage_Helper_Data::processCustomerfile()",null,'knolseed.log');

    try{
      // get crontime & interval 
      $customer_csv_time = Mage::getStoreConfig('engage_options/customer/cron_time');
      $customer_interval = $this->interval;

      //if ($customer_csv_time==date("H:i"))
      if( $this->checkExecuteTime($customer_csv_time) )
      {   
        // check for queue
        $this->checkForQueue('customer');

        $created_day = Mage::getModel('core/date')->date('Y-m-d');

        // Make process entry
        $this->makeProcessEntry($customer_csv_time, $customer_interval, 'customer');

        // collection items for customer CSV
        $processcollection = Mage::getModel('engage/engage')->getCollection()
        ->addFieldToFilter('type','customer')
        ->addFieldToFilter('created_at', array('eq' => $created_day));
        $customerdata = $processcollection->getData();                    
        
        $totalrecords = count($customerdata);
        
        foreach ($customerdata as $process) {
          // generate customer CSV file
          $this->createCustomerCsv($process['date_start'], $process['date_end'], $this->customerattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '');          
        }
      }

    }catch(Exception $e){
      $errormessage = "Error: Customer data dump failed for ". $customer_csv_time .". Will retry again later" ;

      $this->errorAdminNotification('CustomerCSV-initialize-error','customer',$errormessage,'',true);
    }

  }


  public function getAWSFolderName($type){
    Mage::log("Entry Knolseed_Engage_Helper_Data::getAWSFolderName()",null,'knolseed.log');

    switch ($type)
    {
      case "product":
      return "Product";
      break;

      case "customer":
      return "Customer";
      break;

      case "transaction":
      return "Transaction";
      break;

      case "browsing":
      return "Browsing";
      break;

      case "category":
      return "Category";
      break;

      default:
      return "";
      break;
    }
  }


  /**
   * Push created CSV files to S3 Bucket
   * @param   $from,$to,$attributearray,$process_id,$filename
   * @return  on success, creates customer CSV file & push file to S3 bucket
   */
  public function createCustomerCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution = false) {
    Mage::log("Entry Knolseed_Engage_Helper_Data::createCustomerCsv()",null,'knolseed.log');

    try{
      // File generation path
      $path =  Mage::getBaseDir('var')."/" ;  # if you want to add new folder for csv files just add name of folder in dir with single quote.
      // Load customer object for specified interval
      $model = Mage::getModel('customer/customer'); //getting product model
      $collection = $model->getCollection(); //products collection
      $collection->addAttributeToFilter('updated_at', array('gt' =>$from));
      $collection->addAttributeToFilter('updated_at', array('lt' => $to));

      # Mage::log($collection->getSelect(),null,"knolseed.log") ;

      // Check if this is first time execution for customer
      if($intialexecution == true){
        $totalcustcount = $collection->count();
        $numberofchunk = ceil($totalcustcount/$this->records);

        //$fp = fopen($path.$filename, 'w');
        $filename = "Cust_".date("Ymd")."_1_of_".$numberofchunk.".csv.gz" ;

        // Metadata headers for CSV file
        // Line items 1 as per requirement
        $ctime = date("Ymd", strtotime($from));
        $metaheaders = "";
        //Start Date & End date logic
        $begindate = $collection->getFirstItem()->getCreatedAt();
        $endingdate = Mage::getModel('core/date')->date('Y-m-d 00:00:00');
        $metaheaders = '# "'.$type.'","'.$ctime.'","'.$begindate.'","'.$endingdate.'"'."\n" ;

      }else{
        $ts   = strtotime($createdate);
        $createday = date('Ymd',$ts);
        $metaheaders = "";
        //$metaheaders = "# ".$type.",".$createday.",".$from.",".$to."\n" ;
        $metaheaders = '# "'.$type.'","'.$ctime.'","'.$from.'","'.$to.'"'."\n" ;
      }

      if( !trim($filename)) {
        return false ;
      }

      // attributes count for customer
      $customervaluescount = count($attributearray);
      
      // Open file handler for customer CSV
      // $fp = fopen($path.$filename, 'w');
      $fp = gzopen($path.$filename,'w9');

      // Throw exception if failure happens
      if (! $fp) {            
        throw new Exception("File creation error.");
      }

      gzwrite($fp, $metaheaders);

      // Line items 2 as per requirement
      $metaheadersline2 = "";
      $metaheadersline2 = '# id=\'Customer_id\', email=\'email\''."\n" ;
      //$metaheadersline2 = "# id='Customer_id', email='email'\n" ;
      gzwrite($fp, $metaheadersline2);

      // Headers for cusotmer CSV file
      $headers = "";
      $custatrarray = '"'.implode('","', array_map('trim',$attributearray)).'"';
      $headers = '"Customer_id",'.$custatrarray."\n";
      //fwrite($fp,$headers);
      gzwrite($fp, $headers);

      $attribute_values = "";

      $chunkcounter = $filecounter = 1;
      foreach ($collection as $customers) //loop for getting customers
      {       

        # Mage::log($customers,null,"knolseed.log") ;

        // Load customer attributes
        $customer = Mage::getModel('customer/customer')->load($customers->getId()); 
        $attributes = $customer->getAttributes();
        // str_replace('"','""',$string)
        $attribute_values = '"'.str_replace('"', '""', $customer->getId()).'"';
        # Mage::log('Dumping customer '.$customer->getId(),null,'knolseed.log');

        foreach( $attributearray as $key => $vals )
        {           
          // Assign customer attributes value
          $attributeValue = $customer->getData( $vals ) ;
          
          // get address attributes values
          // Mohan: Is empty strings & nulls handled correctly?
          if( $vals == "default_billing" || $vals == "default_shipping" ) {

            $address = Mage::getModel('customer/address')->load($attributeValue);
            $htmlAddress = $address->format('html');
            $string = (string)$htmlAddress;
            # Mage::log('Key= '.$vals.', Val= '.$string,null,'knolseed.log');

            // $string = ereg_replace("[ \t\n\r]+", " ", $string);
            // $string = str_replace(array('<br/>', ',', '<br />'), ' ', $string);
            // $string = str_replace(array('<br/>', '<br />'), ' ', $string);
            $attribute_values .= ',"'.str_replace('"','""',$string).'"';
            // $attribute_values .= $string ;
          } else {
            // Mohan: Is this regex replace necessary? 
            //  $string = ereg_replace("[\t\n\r]+", "", $attributeValue);
            // $string = str_replace('"','""',$string);

            $attribute_values .= ',"'.str_replace('"','""',$attributeValue).'"';
            # Mage::log('Key= '.$vals.', Val= '.$attributeValue,null,'knolseed.log');

            // $attribute_values .= $string ;
            //$attribute_values .= '"'.str_replace(array('<br/>', ',', '<br />'), ' ', $string).'",';
          }
        }

        $attribute_values .= "\n";
        
        gzwrite($fp, $attribute_values);

        $filepath = $path.$filename ;
        $actual_file_name = $filename ;

        // Mohan: Second condition is redundant.
        if( $this->records == $chunkcounter && $filecounter <= $numberofchunk){
          $chunkcounter = 0;
          $filecounter++;
          //fclose($fp);
          gzclose($fp);

          $observer = new Knolseed_Engage_Model_Observer();
          if($observer->pushFileToS3($filepath, $actual_file_name, 'customer', $process_id)){
            Mage::log("pushFileToS3 returned true", null,'knolseed.log');
            if($process_id){
              // Update customer file pushed flag
              $model = Mage::getModel('engage/engage')->load($process_id);
              $model->setFilePushed(1);
              $model->save(); 
            }
            // remove file after push
            unlink($filepath);
          }
          
          $filename = "Cust_".date("Ymd")."_".$filecounter."_of_".$numberofchunk.".csv.gz" ;

          $fp = gzopen($path.$filename,'w9');
          gzwrite($fp, $metaheaders);
          gzwrite($fp, $metaheadersline2);
          gzwrite($fp, $headers);
        }
        $chunkcounter++;

      }

      gzclose($fp);

      $filepath = $path.$filename ;
      $actual_file_name = $filename ;

      $observer = new Knolseed_Engage_Model_Observer();
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='customer', $process_id) ) {
        if($process_id){
          // Update customer file pushed flag
          $model = Mage::getModel('engage/engage')->load($process_id);
          $model->setFilePushed(1);
          $model->save(); 
        }
        // remove file after push
        unlink($filepath);
        return true;
      }

    }catch(Exception $e){
      // Check if critical error or retriable error
      $kf_item = Mage::getModel('engage/engage')->load($process_id);
      $critical = ($kf_item->getAttempt() >= 1) ? true : false ;

      // Admin notification
      //$this->errorAdminNotification('customerCSV','customer',$e->getMessage(),$filename,$critical);

      $errormessage = "Critical Error!  Customer data dump failed for ". $from ." ". $to ." Please email support@knolseed.com about this error, and we will help you to fix this problem." ;

      // Admin notification
      $this->errorAdminNotification('customerCSV','customer',$errormessage,$filename,$critical);

      // Update attempt counts for RETRIABLE errors 
      $this->updatedAttempts($process_id);

      return false;
    }

  }


  /**
   * Push created CSV files to S3 Bucket
   * @param   $type='customer'||'product'
   * @return  on success, creates customer CSV file OR push file to S3 bucket
   */
  public function checkForQueue($type){
    Mage::log("Entry Knolseed_Engage_Helper_Data::checkForQueue()",null,'knolseed.log');

    try{
      // queue collection
      $processcollection = Mage::getModel('engage/engage')->getCollection()
      ->addFieldToFilter('type',$type)
      ->addFieldToFilter('file_pushed', 0);
      # All the records for the queue
      $queuedata = $processcollection->getData();                   

      // total records in queue
      $totalrecords = count($queuedata);
      $path =  Mage::getBaseDir('var')."/";
      
      if($totalrecords > 0)
      {
        # All entries need retries.
        foreach ($queuedata as $process) {
          if($type == 'customer') {
            # create CSV file if file not generated
            $this->createCustomerCsv($process['date_start'], $process['date_end'], $this->customerattributes, $process['process_id'], $process['filename']) ;
          }else if($type == 'product'){
            # create CSV file if file not generated
            $this->createProductCsv($process['date_start'], $process['date_end'], $this->productattributes, $process['process_id'], $process['filename']) ;
          }
        }
      }
    }catch(Exception $e){
      $errormessage = "Error: Data dump failed. Will retry again later" ;
      $this->errorAdminNotification('Queue-checking-error','checkqueue',$errormessage,'',true);
    }

  } 


  /**
   * Push created CSV files to S3 Bucket
   * @param   $time,$interval,$type
   * @return  on success, creates kf process entry into kf_cron_process table
   */
  public function makeProcessEntry($time, $interval, $type){
    Mage::log("Entry Knolseed_Engage_Helper_Data::makeProcessEntry()",null,'knolseed.log');

    // Getting last execution day for DB lock scenario
    $collection = Mage::getModel('engage/engage')->getCollection()->addFieldToFilter('type', $type);
    
    $lastday = $collection->getLastItem()->getCreatedAt();
    
    // get hours minutes
    //list($hours,$minute) = explode(":", $time) ;
    $hours = $minute = 0 ;

    // Get collection count for kf_cron_process
    $totalcollectionitem = $collection->getData();
    $totalitmes = count($totalcollectionitem);

    // Check if kf_cron_process table is empty
    if($totalitmes == 0)
    {         
      $missedexecutiondays = 0;

      // Code for first time push to S3 
      // Getting all products & customers data
      $cust_filename = "Cust_".date("Ymd")."_1_of_1.csv.gz";
      $prod_filename = "Prod_".date("Ymd")."_1_of_1.csv.gz";
      $start_date = "0000-00-00 00:00:00";
      $end_date = date("Y-m-d H:i:00", mktime($hours, $minute, 0, date("m"), date("d"), date("Y")) ) ;

      //Call Create customer function
      
      $file_pushed = false;
      if($type == 'customer')
        $file_pushed = $this->createCustomerCsv($start_date, $end_date, $this->customerattributes, '', $cust_filename, 'customer', $end_date, true);
      else
        $file_pushed = $this->createProductCsv($start_date, $end_date, $this->productattributes, '', $prod_filename, 'product', $end_date, true);

      if($file_pushed===true){
        Mage::log("First time upload of ".$type." data. File successfully uploaded!", null, 'knolseed.log');
        $model = Mage::getModel('engage/engage');

        $model->setDateStart($start_date);
        $model->setDateEnd($end_date);
        $model->setFilePushed(1);
        $model->setFilename('');
        $model->setType($type);
        $model->setCreatedAt(Mage::getModel('core/date')->date('Y-m-d H:i:s'));

        $model->save();
      }

    }else{
      // Get current day
      $currentday = strtotime(date('Y-m-d'));
      $lastexecutionday = strtotime($lastday);
      
      $misseddays = $currentday - $lastexecutionday;
      $missedexecutiondays = ceil($misseddays/86400);     
    } 

    // calculate total number of entries
    $totaldbentry = ceil($this->daytimeinminutes/$interval);

    // looping for missedout days for failed scenario
    for($x=($missedexecutiondays-1);$x>=0;$x--){
      for($i=0;$i<$totaldbentry;$i++){
        try{
          //Process start date 
          $date_start = date("Y-m-d H:i:00", mktime($hours-24, $minute+($interval*$i), 0, date("m"), date("d")-$x, date("Y")) ) ;
          //Process end date
          $date_end   = date("Y-m-d H:i:00", mktime($hours-24, $minute+($interval*($i+1)), 0, date("m"), date("d")-$x, date("Y")) ) ;
          //Date time for filename
          $name       = date("Ymd", mktime($hours, $minute, 0, date("m"), date("d")-$x, date("Y"))) ;   
          // created time
          //$time = Mage::getModel('core/date')->date('Y-m-d H:i:s');

          // filename for customer & products
          if( $type == 'customer')
            $filename = "Cust_".$name."_". ($i+1) ."_of_".$totaldbentry.".csv.gz" ;
          else
            $filename = "Prod_".$name."_". ($i+1) ."_of_".$totaldbentry.".csv.gz" ;

          // DB entry
          $model = Mage::getModel('engage/engage');
          
          $model->setDateStart($date_start);
          $model->setDateEnd($date_end);
          $model->setFilePushed(0);
          $model->setFilename($filename);
          $model->setType($type);
          $model->setCreatedAt(Mage::getModel('core/date')->date('Y-m-d H:i:s'));

          $model->save();
        }catch(Exception $e){
          $errormessage = "Error: Data dump failed. Will retry again later" ;

            // exception for error
          $this->errorAdminNotification('KF table-lock',$type,$errormessage,$filename,true);
        }
      }
    }
  }


  /**
   * Email notification to admin for on failure
   * 
   */ 
  public function errorAdminNotification($errormode, $errortype, $errormessage, $filename="", $critical=false){ 
    Mage::log("Entry Knolseed_Engage_Helper_Data::errorAdminNotification()",null,'knolseed.log');

    try{
      // Log error message
      Mage::log($errormode.','.$errortype.','.$errormessage.','.$filename,null,'knolseed.err');
      Mage::log($errormode.','.$errortype.','.$errormessage.','.$filename,null,'knolseed.log');

      if($critical == true){
        $errormode = 'critical-error';
      }

      $translate = Mage::getSingleton('core/translate');
      $mailTemplate = Mage::getModel('core/email_template');

      // check errormode
      switch ($errormode)
      {
        case "critical-error":
        $templatecode = "critical_error_notification";
        break;

        case "productCSV":
        case "customerCSV":
        default:
        $templatecode = "general_error_notification";
        break;
      }

      // Get general contact information
      $email = Mage::getStoreConfig('trans_email/ident_general/email');
      $name = Mage::getStoreConfig('trans_email/ident_general/name');
      $fromemail = Mage::getStoreConfig('trans_email/ident_support/email');

      $vars = array();
      $vars['filename'] = $filename;
      $vars['errormessage'] = $errormessage;
      $vars['date'] = Mage::getModel('core/date')->date('Y-m-d');
      $vars['type'] = $errortype;
      $vars['subject'] = $errormode;
      $edate = Mage::getModel('core/date')->date('Y-m-d');

      if( $errormode == "GetTemporaryCredentials" ) {

        $subject = 'Error: sync unable to contact Knolseed';
        //$message = 'Error: Unable to contact Knolseed at https://app.knolseed.com/. Will retry again later.';
      }else if($templatecode == "general_error_notification"){

        $subject = 'Error occured "'.$errortype.'": An error occured for "'.$filename.'".';
        //$message = 'Hi there, filename "'.$filename.'" error message "'.$errormessage.'" date "'.$edate.'" type "'.$errortype.'".';
      }else{

        $subject = 'Critical Error! "'.$errortype.'" sync failed for "'.$filename.'"';
        //$message = 'Critical Error! "'.$errormessage.'" for "'.$filename.'". Please email support@knolseed.com about this error, and we will help you to fix this problem.';
      }
      
      $message = $errormessage ;

      $headers = 'From: "'.$fromemail.'"' ;
      mail($email, $subject, $message, $headers);

    }catch(Exception $e){
      Mage::log("An error occurred while sending an email: ".$e->getMessage(),null,'knolseed.err');
      Mage::log("An error occurred while sending an email: ".$e->getMessage(),null,'knolseed.log');
    }

  }


  /**
   * Create Product Attributes CSV file
   *
   * Place created CSV file to AWS S3 bucket
   * 
   * Category dump piggybacked on Product dump.
   * Check for product file, regenerate category & product if not exists or not updated.
   * Dumping categories again is small incremental load, so we can live with this.
   *
   */
  public function processProductfile(){
    Mage::log("Entry Knolseed_Engage_Helper_Data::processProductfile()",null,'knolseed.log');

    try{
      $product_csv_time = Mage::getStoreConfig('engage_options/product/cron_time');
      $product_interval = $this->interval;

      //if ($product_csv_time==date("H:i"))
      if( $this->checkExecuteTime($product_csv_time) )
      { 
        // check queue for previous failure queue itmes
        $this->checkForQueue('product');
        // process entry
        $this->makeProcessEntry($product_csv_time,$product_interval,'product');

        // collection items for product CSV
        $createdday = Mage::getModel('core/date')->date('Y-m-d');
        $processcollection = Mage::getModel('engage/engage')->getCollection()
        ->addFieldToFilter('type','product')
        ->addFieldToFilter('created_at', array('eq' => $createdday));
        
        $productdata = $processcollection->getData();                   

        $totalrecords = count($productdata);
        foreach ($productdata as $process) {
          // generate product CSV file
          $this->createProductCsv($process['date_start'], $process['date_end'], $this->productattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '') ;
        }         
      }

    }catch(Exception $e){
      $errormessage = "Critical Error!  Product data dump failed for ". $product_csv_time .". Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      $this->errorAdminNotification('ProductCSV-initialize-error','product',$e->getMessage(),'',true);

    }

  }



  /**
   * Create Product Attributes CSV file
   *
   * Place created CSV file to AWS S3 bucket
   * 
   */ 
  public function createProductCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution = false){
    Mage::log("Entry Knolseed_Engage_Helper_Data::createProductCsv()", null, 'knolseed.log');
    Mage::log("From=".$from.", To=".$to.", Type=".$type.", CreateDate=".$createdate, null, 'knolseed.log');
    Mage::log("attributearray=".$attributearray, null, 'knolseed.log');

    $this->createCategoryCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution);

    try{
      // get product item collection for defined interval
      $collection = Mage::getModel('catalog/product')->getCollection();
      $collection->addAttributeToFilter('updated_at', array('gt' =>$from));
      $collection->addAttributeToFilter('updated_at', array('lt' => $to));

      // CSV file save path
      $path =  Mage::getBaseDir('var')."/";  # if you want to add new folder for csv files just add name of folder in dir with single quote.

      // Check if this is first time execution for customer
      if($intialexecution == true){
        $totalcustcount = $collection->count();
        $numberofchunk = ceil($totalcustcount/$this->records);

        //$fp = fopen($path.$filename, 'w');
        $filename = "Prod_".date("Ymd")."_1_of_".$numberofchunk.".csv.gz" ;

        // Metadata headers for CSV file
        // Line items 1 as per requirement
        $ctime = date("Ymd", strtotime($from));
        $metaheaders = "";

        //Start Date & End date logic
        $begindate = $collection->getFirstItem()->getCreatedAt();
        $endingdate = Mage::getModel('core/date')->date('Y-m-d 00:00:00');
        $metaheaders = '# "'.$type.'","'.$ctime.'","'.$begindate.'","'.$endingdate.'"'."\n" ;
      }else{
        $ts   = strtotime($createdate);
        $createday = date('Ymd',$ts);
        $metaheaders = "";
        $metaheaders = '# "'.$type.'","'.$createday.'","'.$from.'","'.$to.'"'."\n" ;
      }

      // product attributes list
      $productvaluescount = count($attributearray);
      $field_names_arr = array();
      foreach ($attributearray as $field_name) {
        if($field_name != "sku" && $field_name != "category_ids"){
          $field_names_arr[] = $field_name;
        }
      }
      Mage::log("field_names_arr=".print_r($field_names_arr, true), null, 'knolseed.log');
      Mage::log("field_names_arr length=".print_r(count($field_names_arr), true), null, 'knolseed.log');

      // Gotcha? Is the 'trim' complicating field lookups?
      $product_Attr_str = '"'.implode('","', array_map('trim', $field_names_arr)).'"';
      Mage::log("product_Attr_str=".$product_Attr_str, null, 'knolseed.log');

      if( !trim($filename)) {
        return false ;
      }
      
      // open CSV for product
      //$fp = fopen($path.$filename, "w");
      $fp = gzopen($path.$filename,'w9');
      // throw exception if file opening file
      if (! $fp) {
        throw new Exception("File creation error.");
      }

      gzwrite($fp,$metaheaders);
      
      // Line items 2 as per requirement
      $metaheadersline2 = "";
      $metaheadersline2 = '# id=\'Sku\', url=\'url_key\',category=\'category_ids\''."\n" ;
      gzwrite($fp, $metaheadersline2);

      // headers for product CSV file
      $headers = '"product_id","Sku",'.$product_Attr_str.',"category_ids"'."\n";
      Mage::log("headers=".$headers, null, 'knolseed.log');

      //fwrite($fp,$headers);
      gzwrite($fp,$headers);

      $chunkcounter = $filecounter = 1;
      foreach ($collection as $products) //loop for getting products
      {
        // load product
        $pid = $products->getId();
        $attribute_values = '"'.str_replace('"', '""', $pid).'"';

        $product = Mage::getModel('catalog/product')->load($products->getId());
        $attributes = $product->getAttributes();

        $attribute_values .= ',"'.str_replace('"', '""', $product->getSku()).'"';
        Mage::log("Starting loop for SKU=".$product->getSku(), null, 'knolseed.log');
        
        // Iterate list of attributes for this product
        $productvaluescount = count($field_names_arr);
        for($i=0;$i<$productvaluescount;$i++)
        {
          $attributeName = $field_names_arr[$i];
          Mage::log("attributeName=".$attributeName, null, 'knolseed.log');
          
          if($attributeName!='sku')
          {
            Mage::log("Its not sku", null, 'knolseed.log');

            $attributeValue = null;     
            if(array_key_exists($attributeName , $attributes))
            {
              Mage::log("Array key exists", null, 'knolseed.log');
              $attributesobj = $attributes["{$attributeName}"];

              if( $attributesobj->getAttributeCode() == "category_ids" ){
                continue;
              }elseif($attributesobj->getAttributeCode() == "media_gallery")
              {
                Mage::log("Got media_gallery", null, 'knolseed.log');

                $attributes = $product->getTypeInstance(true)->getSetAttributes($product);

                $galleryData = $product->getData('media_gallery');

                $stringimg = '';
                foreach ($galleryData['images'] as &$image) {
                  $finalpath = $image['file'];
                  $stringimg .= $finalpath.',';
                }
                $attributeValue = rtrim($stringimg, ",");
                if(strlen($attributeValue) ==0){
                  $attribute_values .= ',""';
                }else{
                  $attribute_values .= ',"'.str_replace('"','""',$attributeValue).'"';               
                }

              }elseif($attributesobj->getAttributeCode() == "url_key")
              {       
                Mage::log("Got url_key", null, 'knolseed.log');
                $categoryIds = $product->getCategoryIds();
                $string = '';
                if(count($categoryIds) ){
                  $y=0;
                  foreach($categoryIds as $catid){                
                    $CategoryId = $catid[$y];

                    $_category = Mage::getModel('catalog/category')->load($CategoryId);
                    $url = $product->getUrlPath($_category);
                    if(substr($url, 0, 1) != '/'){
                      $url = substr_replace($url, '/', 0, 0);
                    }

                    $string .= $url.' ';
                  }
                  $urltobeencode = rtrim($string, ",");
                  # $attributeValue = urlencode($urltobeencode);
                  $attributeValue = $urltobeencode;
                  if(strlen($attributeValue) == 0){
                    $attribute_values .= ',""';
                  }else{
                    $attribute_values .= ',"'.str_replace('"','""',$attributeValue).'"';               
                  }

                }
              }elseif( $attributesobj->getAttributeCode() == "activation_information" ){
                Mage::log("Got activation_information. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "description" ){
                Mage::log("Got description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "meta_description" ){
                Mage::log("Got meta_description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "short_description" ){
                Mage::log("Got short_description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "in_depth" ){
                Mage::log("Got in_depth. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "sku" ){
                Mage::log("Got sku. Skipping...", null, 'knolseed.log');
                // $attribute_values .= ',""';
              }else{
                Mage::log("Got ".$attributesobj->getAttributeCode(), null, 'knolseed.log');

                $attributeValue = $attributesobj->getFrontend()->getValue($product);
                //$attributeValue = str_replace(",", "",$attributeValue);
                # if(strlen($attributeValue) >=   '50'){
                  # $attributeValue = wordwrap($attributeValue,150,"\n",TRUE);
                # }

                if( is_array($attributeValue)) {
                  Mage::log("Is an array:".$attributeValue, null, 'knolseed.log');
                  $attribute_values .= ',"'.str_replace('"', '""', implode(",", $attributeValue)).'"';
                }else{
                  Mage::log("Is NOT an array:".$attributeValue, null, 'knolseed.log');
                  $string=str_replace('"','""',$attributeValue);
                  $attribute_values .= ',"'.$string.'"';
                }
              }

            } // if(array_key_exists($attributeName , $attributes))
            else{
              // Attribute doesnt exist for this particular product. Just dump a default value (blank str)
              Mage::log("Array Key does not exist for attributeName=".$attributeName, null, 'knolseed.log');
              if($attributeName == "category_ids"){
                Mage::log("Its category_ids, so skipping...", null, 'knolseed.log');
              }else{
                Mage::log("Adding default = blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }
            }

            // assign attribute value

          } // if($attributeName!='sku')

        }

        Mage::log("Now adding categories...", null, 'knolseed.log');
        // get categories names
        $categoryIds = $product->getCategoryIds();
        Mage::log("Dumping categoryIds=".print_r($categoryIds, true), null, 'knolseed.log');

        if(count($categoryIds) ){
          $catNames = array();
          $z=0;
          $attributeValue = "";
          foreach($categoryIds as $catid){
            Mage::log("Dumping catid=".print_r($catid,true), null, 'knolseed.log');

            # $CategoryId = $catid[$z];
            # Mage::log("Found CategoryId=".print_r($CategoryId,true), null, 'knolseed.log');

            $_category = Mage::getModel('catalog/category')->load($catid);

            $catName = $_category->getName();
            Mage::log("Found cat name=".print_r($catName,true), null, 'knolseed.log');            

            if($catName && strlen($catName)>0){
              $catNames[] = $_category->getName();
            }
          }
          $attributeValue = implode(",",$catNames);              
          Mage::log("Adding category_ids = ".print_r($attributeValue,true), null, 'knolseed.log');          
          $attribute_values .= ',"'.str_replace('"', '""', $attributeValue).'"';
        }

        $attribute_values .= "\n";

        gzwrite($fp, $attribute_values);

        $filepath = $path.$filename ;
        $actual_file_name = $filename ;

        if( $this->records == $chunkcounter && $filecounter < $numberofchunk){
          $chunkcounter = 0;
          $filecounter++;
            //fclose($fp);
          gzclose($fp);

          $observer = new Knolseed_Engage_Model_Observer();
          if( $observer->pushFileToS3($filepath, $actual_file_name, 'product', $process_id) ) {
            Mage::log("pushFileToS3 returned true", null,'knolseed.log');
            if($process_id){
              // Update customer file pushed flag   
              $model = Mage::getModel('engage/engage')->load($process_id);
              $model->setFilePushed(1);
              $model->save(); 
            }
              // remove file after push
            unlink($filepath);
          }

          $filename = "Prod_".date("Ymd")."_".$filecounter."_of_".$numberofchunk.".csv.gz" ;

          $fp = gzopen($path.$filename,'w9');
          gzwrite($fp, $metaheaders);
          gzwrite($fp, $metaheadersline2);
          gzwrite($fp, $headers);
        }

        $chunkcounter++;

      } // foreach ($collection as $products)

      gzclose($fp);

      $filepath = $path.$filename ;
      $actual_file_name = $filename ;

      $observer = new Knolseed_Engage_Model_Observer();
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='product', $process_id) ) {
        Mage::log("pushFileToS3 returned true", null,'knolseed.log');
        if($process_id){
          // Update customer file pushed flag
          $model = Mage::getModel('engage/engage')->load($process_id);
          $model->setFilePushed(1);
          $model->save(); 
        }
          // remove file after push
        unlink($filepath);
      
        return true;
      }

    }catch(Exception $e){       
        // Check if critical error or retriable error
      $kf_item = Mage::getModel('engage/engage')->load($process_id);
      $critical = ($kf_item->getAttempt() >= 1) ? true : false ;

      $errormessage = "Critical Error!  Product data dump failed for ". $from ." ". $to ." with error ". $filename ." Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
        // Admin notification 
      $this->errorAdminNotification('productCSV','product',$errormessage,$filename,$critical);

        // Update attempt counts for RETRIABLE errors 
      Mage::helper('engage')->updatedAttempts($process_id);

      return false;
    }

  }


  /**
   * Create and dump category information
   *
   */
  public function createCategoryCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution = false){
    Mage::log("Entry Knolseed_Engage_Helper_Data::createCategoryCsv()", null, 'knolseed.log');
    Mage::log("From=".$from.", To=".$to.", Type=".$type.", CreateDate=".$createdate.", Filename=".$filename, null, 'knolseed.log');

    try{

      // CSV file save path
      $path =  Mage::getBaseDir('var')."/";  # if you want to add new folder for csv files just add name of folder in dir with single quote.
      $filename = str_replace("Prod", "Cat", $filename);

      $ts   = strtotime($createdate);
      $createday = date('Ymd',$ts);
      $metaheaders = "";
      $metaheaders = '# "Category","'.$createday.'","'.$from.'","'.$to.'"'."\n" ;

      if( !trim($filename)) {
        Mage::log('Category Dump: Filename is blank! ',null,'knolseed.log');
        $filename = "Cat_".date("Ymd")."_1_of_1.csv.gz" ;
        Mage::log("Category Dump: Filename is reset to: ".$filename,null,'knolseed.log');
      }
      
      $fp = gzopen($path.$filename,'w9');
      // throw exception if file opening file
      if (! $fp) {            
        throw new Exception("Category Dump: File creation error. Check that the ".$path." folder has WRITE permissions");
      }

      gzwrite($fp,$metaheaders);
      $metaheadersline2 = "";
      $metaheadersline2 = '# id=\'id\', name=\'name\',child=\'child_id\''."\n" ;
      gzwrite($fp, $metaheadersline2);

      // headers for product CSV file
      $headers = '"id","name","level","parent_id","child_ids"'."\n";  
      gzwrite($fp, $headers);

      // Now load category data and dump into file.
      /*  $line = blah."\n";
        gzwrite($fp, $attribute_values);
        gzclose($fp);

        $filepath = $path.$filename ;
        $actual_file_name = $filename ;

        $observer = new Knolseed_Engage_Model_Observer();
        if( $observer->pushFileToS3($filepath, $actual_file_name, $type='category', $process_id) ) {
          // remove file after push
          unlink($filepath);
        }
      */

      $category = Mage::getModel('catalog/category');
      $tree = $category->getTreeModel();
      $tree->load();
      $ids = $tree->getCollection()->getAllIds();
      if ($ids){
        $count = 0;
        foreach ($ids as $id){
          $cat = Mage::getModel('catalog/category');
          $cat->load($id);

          $entity_id = $cat->getId();
          $name = $cat->getName();
          $url_key = $cat->getUrlKey();
          $url_path = $cat->getUrlPath();
          $children = $cat->getChildren();
          $level = $cat->getLevel();
          $parent = $cat->getParentId();

          Mage::log("Category: ID=".$entity_id.", Name=".$name.", URL=".$url_path, null, 'knolseed.log');
          Mage::log("Category: ID=".$entity_id.", Children=".$children.", Parent=".$parent, null, 'knolseed.log');
          $count=$count+1;

          # Save to file
          # $headers = '"id","name","level","parent_id","child_ids"'."\n";  

          $line = "\"".$entity_id."\"";
          if($name){
            $line .= ",\"".$name."\"";
          }else{
            $line .= ",\"\"";
          }

          if($level){
            $line .= ",\"".$level."\"";
          }else{
            $line .= ",\"\"";
          }

          if($parent){
            $line .= ",\"".$parent."\"";
          }else{
            $line .= ",\"\"";
          }

          if($children){
            $line .= ",\"".$children."\"";
          }else{
            $line .= ",\"\"";
          }

          $line .= "\n";
          Mage::log("Writing line to file:".$line, null, 'knolseed.log');
          gzwrite($fp, $line);
        }

        Mage::log("Total ".$count." categories found!", null, 'knolseed.log');
        gzclose($fp);

        $filepath = $path.$filename ;
        $actual_file_name = $filename ;
        $observer = new Knolseed_Engage_Model_Observer();
        if( $observer->pushFileToS3($filepath, $actual_file_name, "category", $process_id) ) {
          // remove file after push
          unlink($filepath);
        }

      }else{
        Mage::log("Categories is empty!",null,'knolseed.log');
      }

    }catch(Exception $e){       
        // Check if critical error or retriable error
      $kf_item = Mage::getModel('engage/engage')->load($process_id);
      $critical = ($kf_item->getAttempt() >= 1) ? true : false ;
      $exceptionMsg = $e.getMessage();
      $errormessage = "Critical Error!  Category data dump failed for ".$filename." with error: ".$exceptionMsg.". Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
        // Admin notification 
      $this->errorAdminNotification('categoryCSV','product',$errormessage,$filename,$critical);

        // Update attempt counts for RETRIABLE errors 
      Mage::helper('engage')->updatedAttempts($process_id);
    }

  }


  /**
   * Clear kf_cron_process table for eight days old entries
   *
   */ 
  public function flushAllKfEntries(){
    Mage::log("Entry Knolseed_Engage_Helper_Data::flushAllKfEntries()",null,'knolseed.log');
    
    // remove process entry afetr 8 days
    $removedays = date('Y-m-d', strtotime("-8 day"));
    
    // delete record collection
    $collection = Mage::getModel('engage/engage')->getCollection();
    $collection->addFieldToFilter('created_at', array('eq' =>$removedays));

    $kfcrondata = $collection->getData();

    foreach($kfcrondata as $kfdata){
      // delete each process entry
      $kfdata->delete();
    }
  }


  /**
   * This function updates kf_cron_process table & increments attempt count for any kf process on failure
   *
   */ 
  public function updatedAttempts($processid){
    Mage::log("Entry Knolseed_Engage_Helper_Data::updatedAttempts()",null,'knolseed.log');

    try{
      // Check if processid exists
      if($processid){
        // Load process & update current process
        $kf_item = Mage::getModel('engage/engage')->load($processid);
        $updatedattempt = $kf_item->getAttempt() + 1;

        $kf_item->setAttempt($updatedattempt);
        $kf_item->save();
      }
    }catch(Exception $e){
      $this->errorAdminNotification('Updateattempts-error','updateattempts',$e->getMessage(),'',true);
    } 
  }


}

