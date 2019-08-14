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
  public $records = '10000';
  public $customerattributes = array();
  public $productattributes = array();
  public $categoryAttributes = array();
  public $intervalRange = 30;

  # To be configured appropriately
  public $global_log_level = 'PROD';
  # public $global_log_level = 'DEV';

/* contructing customer and product attributes*/
  public function __construct() {
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::__construct()",null,'knolseed.log');

    date_default_timezone_set( Mage::app()->getStore()->getConfig('general/locale/timezone') );

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

    $this->kslog('DEBUG',"productattributes=".print_r($this->productattributes,true), null, 'knolseed.log');    
  }

/* Creating log */
  public function kslog($loglevel, $msg, $two, $file){
    # Mage::log("In kslog, params=".$loglevel.",".$msg.",".$two.",".$file, null, 'knolseed.log');
    # Mage::log("In kslog, global_log_level=".$this->global_log_level, null, 'knolseed.log');

    switch ($loglevel)
    {
      case 'DEBUG':
      # Mage::log("its debug",null,'knolseed.log');
      # IF its debug, only log in dev env
      switch($this->global_log_level){
        case 'DEV':
        # Mage::log("its debug & dev",null,'knolseed.log');
        Mage::log($msg, $two, $file);
        break;

        default:
        # Mage::log("its debug & not dev",null,'knolseed.log');
        break;
      }
      break;

      default:
      # Mage::log("its not debug",null,'knolseed.log');
      # Log anyway
      Mage::log($msg, $two, $file);
      break;
    }
  }

  /**
   * This method checks next execution time for upload items CSV file.
   * Next upload time
   */
  public function processHistoricalData(){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::processHistoricalData()",null,'knolseed.log');

    $jobtype = 'transaction';
    $clearToRun = false;

    try{
      $uploadtime = Mage::getStoreConfig('upload_options/upload/transaction');
      $this->kslog('DEBUG',"Upload Time = ".print_r($uploadtime,true),null,'knolseed.log');
      
      // return false if there is no time set.
      if( !trim($uploadtime) ) {
        return false ;
      }

      $time = Mage::getStoreConfig('upload_options/upload/time');
      $uploadinfo = explode(",",Mage::getStoreConfig('upload_options/upload/upload_info',Mage::app()->getStore()->getId())); 
      
      //if($uploadtime == Date("Y-m-d H:i"))
      if( $this->checkExecuteTime($uploadtime, $jobtype) )
      {
        // Already running?
        $clearToRun = $this->acquireLock('transaction', date("Ymd"));
        if($clearToRun==false){
          return;
        }

        $this->kslog('DEBUG',"processHistoricalData() - Acquired Lock",null,'knolseed.log');

        // set time backtime
        $coredataobj = new Mage_Core_Model_Config();
        $coredataobj->saveConfig('upload_options/upload/transaction', ' ' ,'default', 0);
        Mage::app()->getStore()->resetConfig();

        $timeinterval = Mage::getStoreConfig('upload_options/upload/timeframe');
        $nextexecutiontime = date('Y-m-d', strtotime($timeinterval." month"));

        // Get total number of days
        $totalnoofdays = $this->numberofdays*$timeinterval;

        $this->kslog('DEBUG',"Starting Datasync for #days = ".print_r($totalnoofdays,true),null,'knolseed.log');

        if(in_array('1', $uploadinfo))
        { 
#          for($i=$totalnoofdays;$i>=0;$i--){
#            $this->getTransactionInfo($i, $uploadtime, $type='transaction', $timeinterval);
#          }
          $filenames = array();
          for($i=$totalnoofdays; $i>=0; $i--){
            // Log error msgs for each exception and continue.
            try {
              $filename = $this->getTransactionInfo($i, $uploadtime, $type='transaction', $timeinterval);
              $filenames[] = $filename;
            } catch (Exception $e1) {
              $errmsg = $e1->getMessage();
              $this->kslog('ERROR', $errmsg, null, 'knolseed.err');
              $this->errorAdminNotification('Transaction-Data-Sync',
                "Error",
                "Error in Transaction Data Sync: ".$errmsg.". Please email support@knolseed.com about this error, and we will help you to fix this problem.",
                '',
                true
                );
            }
          } # for

          if(count($filenames)>0){
            # Upload Manifest file
            $manifest_file_name =  "Txn_".date('Ymd').".gz";
            $observer = new Knolseed_Engage_Model_Observer();
            $observer->addManifestFile($manifest_file_name, $type, '', $filenames);
           }
        }

        if(in_array('2', $uploadinfo))
        {
          for($k=$totalnoofdays;$k>=0;$k--){
            $this->getBrowsingInfo($k, $uploadtime, $type='weblog', $timeinterval);
          }
        }
      }

    }catch(Exception $e){
      $errormessage = "Critical Error! Transaction data sync unable to authenticate with Knolseed. Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      $this->errorAdminNotification('Upload-initialize-error','uploaddata',$errormessage,'',true);
    }

    if($clearToRun==true){
      $this->kslog('DEBUG',"processHistoricalData() - Releasing Lock",null,'knolseed.log');
      $this->releaseLock('transaction', date("Ymd"));      
    }

  }


/* function to print product categories*/
  public function printCategories(){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::printCategories()",null,'knolseed.log');
    # $this->printCategories_1();
  }


/* function to print categories */
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

        $this->kslog('DEBUG',"Category: ID=".$entity_id.", Name=".$name.", URL=".$url_path, null, 'knolseed.log');
        if($level && $level==2){
          $this->kslog('DEBUG',"Its a base category", null, 'knolseed.log');
        }
      }
    }else{
      $this->kslog('DEBUG',"Categories is empty!",null,'knolseed.log');
    }
  }


  /**
   * This method returns information related to sales order item.
   * 
   */ 
  public function getTransactionInfo($count,$startendtime,$type,$duration){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::getTransactionInfo()",null,'knolseed.log');

    try{
      // Get sales order itmes collection
      $collection = Mage::getResourceModel('sales/order_item_collection');

      $createdday = date('Y-m-d',strtotime("-$count day"));
      $filerandate = date('Ymd',strtotime("-$count day"));
      $fileranstartdate = date('Y-m-d 00:00:00',strtotime("-$count day"));
      $fileranenddate = date('Y-m-d 24:00:00',strtotime("-$count day"));

      $collection->getSelect()->joinLeft(array('sfo'=>'sales_flat_order'),
        'main_table.order_id = sfo.entity_id',array('sfo.shipping_amount','sfo.customer_id','sfo.increment_id',
          'sfo.grand_total','sfo.base_currency_code','sfo.order_currency_code','sfo.global_currency_code','sfo.store_currency_code'));

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
      $metaheadersline2 = '#= id=\'Transaction_id\', cid=\'Customer_id\', sku=\'sku\', category=\'Category_Ids\', timestamp=\'Timestamp\', total=\'Transaction Amount\''."\n" ;
      gzwrite($fp, $metaheadersline2);

      //Double quotes logic for headers
      $transaction_id_header = 'Transaction_id';
      $customer_id_header = 'Customer_id';
      $timestamp_header = 'Timestamp';
      $Product_id_header = 'Product_id';
      $sku_header = 'sku';
      $Category_header = 'Category';
      $category_ids_header = 'Category_Ids';
      $Tax_header = 'Tax';
      $Shipping_header = 'Shipping';

      $headers_transaction = '"'.$transaction_id_header.'","'.$customer_id_header.'","'.$timestamp_header.'","'.$Product_id_header.'","'.$sku_header.'","'.$Category_header.'","'.$category_ids_header.'","Transaction Amount","No of items","'.$Tax_header.'","'.$Shipping_header.'","Product Description","base_currency_code","order_currency_code","store_currency_code","global_currency_code"'."\n" ;
      gzwrite($fp, $headers_transaction) ;

      // Iterate through sales order items
      foreach($collection as $col){
        # Mage::log("Found txn:".print_r($col,true),null,'knolseed.log');

        $customer_id = str_replace('"', '""', $col['customer_id']);
        $transaction_id = str_replace('"', '""', $col['increment_id']);
        $timestamp = $col['created_at'];
        $product_id = str_replace('"', '""', $col['product_id']);
        $sku = str_replace('"', '""', $col['sku']);
        $transaction_amount = $col['grand_total'];
        $items = $col['qty_ordered'];
        $tax = $col['tax_amount'];
        $shipping = $col['shipping_amount'];
        $description = str_replace('"', '""', $col['description']);
        $proid = str_replace('"', '""', $col['product_id']);

        $bcc = str_replace('"', '""', $col['base_currency_code']);
        $occ = str_replace('"', '""', $col['order_currency_code']);
        $scc = str_replace('"', '""', $col['store_currency_code']);
        $gcc = str_replace('"', '""', $col['global_currency_code']);

        $product = Mage::getModel('catalog/product')->load($proid);
        
        # Mage::log("Found product:".print_r($product,true),null,'knolseed.log');
        
        $product_price = $product->getFinalPrice() ;
        
        $categorynames = array() ;
        $categoryIds = array();
        foreach( $product->getCategoryIds() as $categoryid ) {
          $categoryIds[] = $categoryid;
          $catagory_model = Mage::getModel('catalog/category');
          $categories = $catagory_model->load($categoryid); // where $id will be the known category id 
          $categorynames[] = $categories->getName();
        }
        $cats = implode(",", $categorynames);
        $cats = str_replace('"', '""', $cats);
        $catids = implode(",", $categoryIds);

        gzwrite($fp, '"'.$transaction_id.'","'.$customer_id.'","'.$timestamp.'","'.$product_id.'","'.$sku.'","'.$cats.'","'.$catids.'","'.$product_price.'","'.$items.'","'.$tax.'","'.$shipping.'","'.$description.'","'.$bcc.'","'.$occ.'","'.$scc.'","'.$gcc.'"'."\n");
        
      }
      gzclose($fp);

      $filepath = $path."/".$filename;
      $actual_file_name = $filename ;
      $observer = new Knolseed_Engage_Model_Observer();
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='transaction', false, '') ) {
        unlink($filepath);
        return $filename;
      }

    }catch(Exception $e){
      $errormessage = "Critical Error!  Transaction data dump failed for ". $createdday ." with error ". $filename ." Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      $this->errorAdminNotification('transactionCSV','transaction',$errormessage,$filename,true);
    }
  }


  /**
   * This method returns information related to visitor's browsing information.
   * 
   */ 
  public function getBrowsingInfo($count,$startendtime,$type,$duration){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::getBrowsingInfo()",null,'knolseed.log');

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

      $this->kslog('DEBUG',"browsing data=".print_r($data,true),null,'knolseed.log');  

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
      $metaheadersline2 = '#= cid=\'Customer_id\', url=\'URL\', timestamp=\'Timestamp\''."\n" ;
      //$metaheadersline2 = "# cid='Customer_id', url='URL', timestamp='Timestamp'\n" ;
      gzwrite($fp, $metaheadersline2);

      $customer_id_header = 'Customer_id';
      $timestamp_header = 'Timestamp';
      $URL_header = 'URL';
      $Product_id_header = 'Product_id';
      $Category_header = 'Category';

      $headers_transaction = '"'.$customer_id_header.'","'.$timestamp_header.'","'.$URL_header.'","'.$Product_id_header.'","'.$Category_header.'"'."\n" ;
      //fputcsv($fp, array('Customer_id','Timestamp','URL','Product_id','Category'));
      gzwrite($fp, $headers_transaction) ;

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
        gzwrite($fp, '"'.str_replace('"', '""', $urlinfo['visitor_id']).'","'.str_replace('"', '""', $urlinfo['visit_time']).'","'.$stringurl.'","'.$productid.'","'.$categoryname.'"'."\n");  
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
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='browsing', false, '') ) {
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
   * 
   * Strategy: 
   *   Prod/Cust
   *    - runtime = Create timestamp in magento timezone with time = runtime.
   *    - now = Create now() in magento timezone.
   *    - if (now-runtime) < 30mins, true. Else false.
   *
   *  Txn/Browse:
   *    - runtime = ts in magento tz with date & time from runtime.
   *    - now = same as above.
   *    - logic = same as above.
   */
  public function checkExecuteTime($runTime, $jobType) {
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::checkExecuteTime()",null,'knolseed.log');
    $this->kslog('DEBUG',"runtime = ".print_r($runTime,true),null,'knolseed.log');

    $fullRunTime = $runTime;
    switch ($jobType)
    {
      case "product":
      case "customer":
        $date = date("Y-m-d");
        if( strcmp("00:00", $runTime) == 0){
          $date = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d")+1, date("Y")) ) ;
        }
        $fullRunTime = $date." ".trim($runTime);
        break;

      default:
        $fullRunTime = $runTime;
        break;
    }

    $runTsInSecs = strtotime($fullRunTime);
    $now = date("Y-m-d H:i:s");
    $nowInSecs = strtotime($now);

    $this->kslog('DEBUG',"Scheduled Time = ".print_r($fullRunTime,true),null,'knolseed.log');
    $this->kslog('DEBUG',"Current Time = ".print_r($now,true),null,'knolseed.log');
    $this->kslog('DEBUG',"Scheduled Time in secs = ".$runTsInSecs,null,'knolseed.log');
    $this->kslog('DEBUG',"Scheduled Time = ".$nowInSecs,null,'knolseed.log');

    if( ($runTsInSecs>=$nowInSecs) && (($runTsInSecs-$nowInSecs)<= $this->intervalRange*60) ){
      $this->kslog('DEBUG',"checkExecuteTime returning true",null,'knolseed.log');
      return true;
    }else{
      $this->kslog('DEBUG',"checkExecuteTime returning false",null,'knolseed.log');
      return false;       
    }
  }


  public function getLockFileName($type, $date){
    return $type."_".date("Ymd").".lock";
  }


  public function getLockFilePath($filename){
    $path =  Mage::getBaseDir('var')."/" ; 
    return $path.$filename;
  }


  public function acquireLock($type, $date){
    $filename = $this->getLockFileName($type, $date);
    $filepath = $this->getLockFilePath($filename);

    if(file_exists($filepath)){
      $this->kslog('DEBUG', "acquireLock() -  File already exists:".$filepath, null, 'knolseed.log');
      return false;
    }else{
      try{
        $lockfile = fopen($filepath, "w");
        fwrite($lockfile, "acquireLock() - Locking for ".print_r($date,true)."\n");
        fclose($lockfile);
        $this->kslog('DEBUG', "acquireLock() -  Acquired Lock : ".$filepath, null, 'knolseed.log');
        return true;
      }catch(Exception $e){
        $this->kslog('ERROR', "Error in creating file:".$e->getMessage(), null, 'knolseed.err');
      }
    }

    $this->kslog('DEBUG', "acquireLock() -  Failed to acquire Lock : ".$filepath, null, 'knolseed.log');
    return false;
  }


  public function releaseLock($type, $date){
    $filename = $this->getLockFileName($type, $date);
    $filepath = $this->getLockFilePath($filename);

    $this->kslog('DEBUG', "releaseLock() -  Releasing Lock : ".$filepath, null, 'knolseed.log');
    unlink($filepath);
  }

  /**
   * Create Customer Attributes CSV file
   *
   * Place created CSV file to AWS S3 bucket
   * 
   */
  public function processCustomerfile(){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::processCustomerfile()",null,'knolseed.log');

    $jobtype = 'customer';
    $clearToRun = false;

    try{
      // get crontime & interval 
      $customer_csv_time = Mage::getStoreConfig('engage_options/customer/cron_time');
      $customer_interval = $this->interval;

      //if ($customer_csv_time==date("H:i"))
      if( $this->checkExecuteTime($customer_csv_time, $jobtype) )
      {
        $all_files_uploaded = array();

        // Already running?
        $clearToRun = $this->acquireLock('customer', date("Ymd"));
        if($clearToRun==false){
          return;
        }

        $this->kslog('DEBUG',"processCustomerfile() - Acquired Lock",null,'knolseed.log');

        // check for queue
        # $this->checkForQueue('customer');
        $filenames = $this->checkForQueue('customer');
        if( !is_null($filenames) ){
          foreach ($filenames as $fn) {
            $all_files_uploaded[] = $fn;
          }
        }

        $created_day = date('Y-m-d');

        // Make process entry
        $filenames = $this->makeProcessEntry($customer_csv_time, $customer_interval, 'customer');
        if( !is_null($filenames) ){
          foreach ($filenames as $fn) {
            $all_files_uploaded[] = $fn;
          }
        }

        // collection items for customer CSV
        $processcollection = Mage::getModel('engage/engage')->getCollection()
        ->addFieldToFilter('type','customer')
        ->addFieldToFilter('created_at', array('eq' => $created_day));
        $customerdata = $processcollection->getData();                    
        
        $totalrecords = count($customerdata);
        
        foreach ($customerdata as $process) {
          // generate customer CSV file
          $filenames = $this->createCustomerCsv($process['date_start'], $process['date_end'], $this->customerattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '');
          foreach($filenames as $fn){
            $all_files_uploaded[] = $fn;
          }          
        }

        if(count($all_files_uploaded)>0){
          # Upload Manifest file
          $manifest_file_name =  "Cust_".date('Ymd').".gz";
          $observer = new Knolseed_Engage_Model_Observer();
          $observer->addManifestFile($manifest_file_name, 'customer', '', $all_files_uploaded);
        }

      }

    }catch(Exception $e){
      $errormessage = "Error: Customer data dump failed for ". $customer_csv_time .". Will retry again later" ;
      $this->errorAdminNotification('CustomerCSV-initialize-error','customer',$errormessage,'',true);
    }

    if($clearToRun==true){
      $this->kslog('DEBUG',"processCustomerfile() - Releasing Lock",null,'knolseed.log');
      $this->releaseLock('customer', date("Ymd"));
    }

  }


/* Creating folder in AWS */

  public function getAWSFolderName($type){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::getAWSFolderName()",null,'knolseed.log');

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
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::createCustomerCsv()",null,'knolseed.log');

    $files = array();

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

        $this->kslog('DEBUG',"filename = ".print_r($filename,true),null,'knolseed.log');

        // Metadata headers for CSV file
        // Line items 1 as per requirement
        $ctime = date("Ymd", strtotime($from));
        $metaheaders = "";
        //Start Date & End date logic
        $begindate = $collection->getFirstItem()->getCreatedAt();
        $endingdate = date('Y-m-d 00:00:00');
        $metaheaders = '# "'.$type.'","'.$ctime.'","'.$begindate.'","'.$endingdate.'"'."\n" ;

      }else{
        $ts   = strtotime($createdate);
        $createday = date('Ymd',$ts);
        $metaheaders = "";
        //$metaheaders = "# ".$type.",".$createday.",".$from.",".$to."\n" ;
        $metaheaders = '# "'.$type.'","'.$ctime.'","'.$from.'","'.$to.'"'."\n" ;
      }

      if( !trim($filename)) {
        return $files ;
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
      $metaheadersline2 = '#= id=\'Customer_id\', email=\'email\''."\n" ;
      //$metaheadersline2 = "# id='Customer_id', email='email'\n" ;
      gzwrite($fp, $metaheadersline2);

      // Headers for cusotmer CSV file
      $headers = "";
      $custatrarray = '"'.implode('","', array_map('trim',$attributearray)).'"';
      $headers = '"Customer_id",'.$custatrarray."\n";
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
          if($observer->pushFileToS3($filepath, $actual_file_name, 'customer', false, $process_id)){
            $files[] = $actual_file_name;
            $this->kslog('DEBUG', "pushFileToS3 returned true", null, 'knolseed.log');

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
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='customer', false, $process_id) ) {
        $files[] = $actual_file_name;
        if($process_id){
          // Update customer file pushed flag
          $model = Mage::getModel('engage/engage')->load($process_id);
          $model->setFilePushed(1);
          $model->save(); 
        }
        // remove file after push
        unlink($filepath);
        return $files;
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

      return $files;
    }
  }


  /**
   * Push created CSV files to S3 Bucket
   * @param   $type='customer'||'product'
   * @return  on success, returns list of all files pushed to S3
   */

  public function checkForQueue($type){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::checkForQueue()",null,'knolseed.log');

    $files_pushed = array();

    try{
      // queue collection
      $processcollection = Mage::getModel('engage/engage')->getCollection() //data from Kf_cron_process
        ->addFieldToFilter('type',$type)
        ->addFieldToFilter('file_pushed', 0);

      # All the records for the queue
      $queuedata = $processcollection->getData();                   
      $this->kslog('DEBUG',"queuedata = ".print_r($queuedata,true),null,'knolseed.log');

      // total records in queue
      $totalrecords = count($queuedata);
      $this->kslog('DEBUG','$totalrecords = '.print_r($totalrecords,true),null,'knolseed.log');

      $path =  Mage::getBaseDir('var')."/";
      $this->kslog('DEBUG','$path = '.print_r($path,true),null,'knolseed.log');
      
      if($totalrecords > 0)
      {
        # All entries need retries.
        foreach ($queuedata as $process) {
          $filenames = array();
          if($type == 'customer') {
            # create CSV file if file not generated
            $filenames = $this->createCustomerCsv($process['date_start'], $process['date_end'], $this->customerattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '') ;
          }else if($type == 'product'){
            # create CSV file if file not generated
            $filenames = $this->createProductCsv($process['date_start'], $process['date_end'], $this->productattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '') ;
          }
        }

        foreach($filenames as $fn){
          $this->kslog('INFO',"Dumped file:".$fn, null, 'knolseed.log');
          $files_pushed[] = $fn;
        }

      }
    }catch(Exception $e){
      $errormessage = "Error: Data dump failed. Will retry again later" ;
      $this->errorAdminNotification('Queue-checking-error','checkqueue',$errormessage,'',true);
    }

    return $files_pushed;

  }


  /**
   * Push created CSV files to S3 Bucket
   * @param   $time,$interval,$type
   * @return  on success, creates kf process entry into kf_cron_process table
   */
  public function makeProcessEntry($time, $interval, $type){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::makeProcessEntry()",null,'knolseed.log');

    $filenames = array();

    // Getting last execution day for DB lock scenario
    // FIXME: Should this also filter on file_pushed=0 ?
    $collection = Mage::getModel('engage/engage')->getCollection()->addFieldToFilter('type', $type);
    
    $lastday = $collection->getLastItem()->getCreatedAt();
    $this->kslog('DEBUG',"Latest Day : ".print_r($lastday,true),null,'knolseed.log'); 
   
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
      
      $files_pushed = array();
      if($type == 'customer')
        $files_pushed = $this->createCustomerCsv($start_date, $end_date, $this->customerattributes, '', $cust_filename, 'customer', $end_date, true);
      else
        $files_pushed = $this->createProductCsv($start_date, $end_date, $this->productattributes, '', $prod_filename, 'product', $end_date, true);

      if( !is_null($files_pushed) && count($files_pushed)>0 ){
        $filenames = $files_pushed;
        $this->kslog('INFO',"First time upload of ".$type." data. File successfully uploaded!", null, 'knolseed.log');

        $created_at = date('Y-m-d H:i:s');
        $this->kslog('DEBUG',"created_at = ".$created_at, null, 'knolseed.log');

        $model = Mage::getModel('engage/engage');
        $model->setDateStart($start_date);
        $model->setDateEnd($end_date);
        $model->setFilePushed(1);
        $model->setFilename('');
        $model->setType($type);
        $model->setCreatedAt($created_at);
        $model->save();
      }

    }else{
      $this->kslog('INFO',"MakeProcessEntry: Non-first time scenario", null, 'knolseed.log');
      
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

          // filename for customer & products
          if( $type == 'customer')
            $filename = "Cust_".$name."_". ($i+1) ."_of_".$totaldbentry.".csv.gz" ;
          else
            $filename = "Prod_".$name."_". ($i+1) ."_of_".$totaldbentry.".csv.gz" ;

          $created_at = date('Y-m-d H:i:s');
          $this->kslog('DEBUG',"created_at = ".$created_at, null, 'knolseed.log');

          // DB entry
          $model = Mage::getModel('engage/engage');
          
          $model->setDateStart($date_start);
          $model->setDateEnd($date_end);
          $model->setFilePushed(0);
          $model->setFilename($filename);
          $model->setType($type);
          $model->setCreatedAt($created_at);

          $model->save();
          $this->kslog('INFO',"MakeProcessEntry: Added db entry for:".$filename, null, 'knolseed.log');
        }catch(Exception $e){
          $errormessage = "Error: Data dump failed. Will retry again later" ;

            // exception for error
          $this->errorAdminNotification('KF table-lock',$type,$errormessage,$filename,true);
        }
      }
    }

    return $filenames;
  }


  /**
   * Email notification to admin for on failure
   * 
   */ 
  public function errorAdminNotification($errormode, $errortype, $errormessage, $filename="", $critical=false){ 
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::errorAdminNotification()",null,'knolseed.log');

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
      $vars['date'] = date('Y-m-d');
      $vars['type'] = $errortype;
      $vars['subject'] = $errormode;
      $edate = date('Y-m-d');

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
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::processProductfile()",null,'knolseed.log');

    $jobtype = 'product';
    $clearToRun = false;

    try{
      
      $product_csv_time = Mage::getStoreConfig('engage_options/product/cron_time');
      $product_interval = $this->interval;

      $this->kslog('DEBUG',"product_csv_time = ".print_r($product_csv_time,true),null,'knolseed.log');

      //if ($product_csv_time==date("H:i"))
      $retval = $this->checkExecuteTime($product_csv_time, $jobtype);
      $this->kslog('DEBUG',"return value of checkExecuteTime = ".print_r($retval,true), null, 'knolseed.log');
      if( $retval )
      {
        $this->kslog('DEBUG',"checkExecuteTime",null,'knolseed.log');
        
        $all_files_uploaded = array();

        // Already running?
        $clearToRun = $this->acquireLock('product', date("Ymd"));
        if($clearToRun==false){
          return;
        }
        $this->kslog('DEBUG',"processProductfile() - Acquired Lock",null,'knolseed.log');

        // check queue for previous failure queue items
        $filenames = $this->checkForQueue('product');
        if( !is_null($filenames) ){
          foreach ($filenames as $fn){
            $all_files_uploaded[] = $fn;
          }
        }
        $this->kslog('DEBUG',"checkForQueue",null,'knolseed.log');
        
        // process entry
        $filenames = $this->makeProcessEntry($product_csv_time,$product_interval,'product');
        if( !is_null($filenames) ){
          foreach ($filenames as $fn){
            $all_files_uploaded[] = $fn;
          }
        }
        $this->kslog('DEBUG',"makeProcessEntry",null,'knolseed.log');

        // collection items for product CSV
        $createdday = date('Y-m-d');

        $this->kslog('DEBUG',"Created Day = ".print_r($createdday,true),null,'knolseed.log');
         
        $processcollection = Mage::getModel('engage/engage')->getCollection()
        ->addFieldToFilter('type','product')
        ->addFieldToFilter('created_at', array('eq' => $createdday));
                
        $productdata = $processcollection->getData();                   

        $totalrecords = count($productdata);
        foreach ($productdata as $process) 
        {
          $this->kslog('DEBUG',"process = ".print_r($process,true),null,'knolseed.log');
          
          // generate product CSV file
          $filenames = $this->createProductCsv($process['date_start'], $process['date_end'], 
            $this->productattributes, $process['process_id'], $process['filename'], $process['type'], $process['created_at'], '') ;
          
          if( !is_null($filenames) ){
            foreach ($filenames as $fn){
              $all_files_uploaded[] = $fn;
            }
          }

        }

        if(count($all_files_uploaded) > 0){
          # Upload Manifest file
          $manifest_file_name =  "Prod_".date('Ymd').".gz";
          $observer = new Knolseed_Engage_Model_Observer();
          $observer->addManifestFile($manifest_file_name, 'product', '', $all_files_uploaded);
        }

      } // If(retval)

    }catch(Exception $e){
      $errormessage = "Critical Error!  Product data dump failed for ". $product_csv_time .". Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      $this->errorAdminNotification('ProductCSV-initialize-error','product',$e->getMessage(),'',true);
    }

    if($clearToRun==true){
      $this->kslog('DEBUG',"processProductfile() - Releasing Lock",null,'knolseed.log');
      $this->releaseLock('product', date("Ymd"));      
    }

  }


  /**
   * Create Product Attributes CSV file
   *
   * Place created CSV file to AWS S3 bucket
   * 
   */ 
  public function createProductCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution = false){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::createProductCsv()", null, 'knolseed.log');
    $this->kslog('DEBUG', "From=".$from.", To=".$to.", Type=".$type.", CreateDate=".$createdate, null, 'knolseed.log');
    $this->kslog('DEBUG',"attributearray=".$attributearray, null, 'knolseed.log');

    $files = array();

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
        $endingdate = date('Y-m-d 00:00:00');
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
      $this->kslog('DEBUG',"field_names_arr=".print_r($field_names_arr, true), null, 'knolseed.log');
      $this->kslog('DEBUG',"field_names_arr length=".print_r(count($field_names_arr), true), null, 'knolseed.log');

      // Gotcha? Is the 'trim' complicating field lookups?
      $product_Attr_str = '"'.implode('","', array_map('trim', $field_names_arr)).'"';
      $this->kslog('DEBUG',"product_Attr_str=".$product_Attr_str, null, 'knolseed.log');

      if( !trim($filename)) {
        return $files ;
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
      $metaheadersline2 = '#= id=\'Sku\', url=\'url_key\',category=\'category_ids\''."\n" ;
       $this->kslog('DEBUG',"metaheadersline2=".$metaheadersline2, null, 'knolseed.log');
      gzwrite($fp, $metaheadersline2);

      // headers for product CSV file
      $headers = '"product_id","Sku","created_at","updated_at",'.$product_Attr_str.',"category_ids"'."\n"; //"created_at,updated_at" by dinesh
      $this->kslog('DEBUG',"headers=".$headers, null, 'knolseed.log');

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

        $this->kslog('DEBUG',"Starting loop for SKU=".$product->getSku(), null, 'knolseed.log');

        //added column for created_date
        $created_at = $product->getCreatedAt();
        if($created_at===null){
          $attribute_values .= ',""';
        }else{
          $attribute_values .= ',"'.$created_at.'"';        
        }

        //added column for updated_date 
        $updated_at = $product->getUpdatedAt();
        if($updated_at===null){
          $attribute_values .= ',""';
        }else{
          $attribute_values .= ',"'.$updated_at.'"';
        }

        
        // Iterate list of attributes for this product
        $productvaluescount = count($field_names_arr);
        for($i=0;$i<$productvaluescount;$i++)
        {
          $attributeName = $field_names_arr[$i];
          # Mage::log("attributeName=".$attributeName, null, 'knolseed.log');
          
          if($attributeName!='sku')
          {
            $this->kslog('DEBUG',"Its not sku", null, 'knolseed.log');

            $attributeValue = null;     
            if(array_key_exists($attributeName , $attributes))
            {
              $this->kslog('DEBUG',"Array key exists", null, 'knolseed.log');
              $attributesobj = $attributes["{$attributeName}"];

              if( $attributesobj->getAttributeCode() == "category_ids" ){
                continue;
              }elseif($attributesobj->getAttributeCode() == "media_gallery")
              {
                $this->kslog('DEBUG',"Got media_gallery", null, 'knolseed.log');

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
                $this->kslog('DEBUG',"Got url_key", null, 'knolseed.log');
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
                $this->kslog('DEBUG',"Got activation_information. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "description" ){
                $this->kslog('DEBUG',"Got description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "meta_description" ){
                $this->kslog('DEBUG',"Got meta_description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "short_description" ){
                $this->kslog('DEBUG',"Got short_description. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "in_depth" ){
                $this->kslog('DEBUG',"Got in_depth. Adding blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }elseif( $attributesobj->getAttributeCode() == "sku" ){
                $this->kslog('DEBUG',"Got sku. Skipping...", null, 'knolseed.log');
                // $attribute_values .= ',""';
              }else{
                $this->kslog('DEBUG',"Got ".$attributesobj->getAttributeCode(), null, 'knolseed.log');

                $attributeValue = $attributesobj->getFrontend()->getValue($product);
                //$attributeValue = str_replace(",", "",$attributeValue);
                # if(strlen($attributeValue) >=   '50'){
                  # $attributeValue = wordwrap($attributeValue,150,"\n",TRUE);
                # }

                if( is_array($attributeValue)) {
                  $this->kslog('DEBUG',"Is an array:".$attributeValue, null, 'knolseed.log');
                  $attribute_values .= ',"'.str_replace('"', '""', implode(",", $attributeValue)).'"';
                }else{
                  $this->kslog('DEBUG',"Is NOT an array:".$attributeValue, null, 'knolseed.log');
                  $string=str_replace('"','""',$attributeValue);
                  $attribute_values .= ',"'.$string.'"';
                }
              }

            } // if(array_key_exists($attributeName , $attributes))
            else{
              // Attribute doesnt exist for this particular product. Just dump a default value (blank str)
              $this->kslog('DEBUG',"Array Key does not exist for attributeName=".$attributeName, null, 'knolseed.log');
              if($attributeName == "category_ids"){
                $this->kslog('DEBUG',"Its category_ids, so skipping...", null, 'knolseed.log');
              }else{
                $this->kslog('DEBUG',"Adding default = blankstr", null, 'knolseed.log');
                $attribute_values .= ',""';
              }
            }

            // assign attribute value

          } // if($attributeName!='sku')

        }

        $this->kslog('DEBUG',"Now adding categories...", null, 'knolseed.log');
        // get categories names
        $categoryIds = $product->getCategoryIds();
        $this->kslog('DEBUG',"Dumping categoryIds=".print_r($categoryIds, true), null, 'knolseed.log');

        if(count($categoryIds) ){
          $catNames = array();
          $z=0;
          $attributeValue = "";
          $attributeValue = implode(",",$categoryIds);              
          $this->kslog('DEBUG',"Adding category_ids = ".print_r($attributeValue,true), null, 'knolseed.log');          
          $attribute_values .= ',"'.str_replace('"', '""', $attributeValue).'"';
        }else{
          $attribute_values .= ',';
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
          if( $observer->pushFileToS3($filepath, $actual_file_name, 'product', false, $process_id) ) {
            $files[] = $actual_file_name;
            $this->kslog('DEBUG', "pushFileToS3 returned true", null, 'knolseed.log');

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
      if( $observer->pushFileToS3($filepath, $actual_file_name, $type='product', false, $process_id) ) {
        $files[] = $actual_file_name;
        $this->kslog('DEBUG', "pushFileToS3 returned true", null, 'knolseed.log');

        if($process_id){
          // Update customer file pushed flag
          $model = Mage::getModel('engage/engage')->load($process_id);
          $model->setFilePushed(1);
          $model->save(); 
        }
          // remove file after push
        unlink($filepath);
      
        return $files;
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

      return $files;
    }

  }


  /**
   * Create and dump category information
   *
   */
  public function createCategoryCsv($from, $to, $attributearray, $process_id, $filename, $type, $createdate, $intialexecution = false){
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::createCategoryCsv()", null, 'knolseed.log');
    $this->kslog('DEBUG',"From=".$from.", To=".$to.", Type=".$type.", CreateDate=".$createdate.", Filename=".$filename, null, 'knolseed.log');

    # Category dump only if first product fragment dump.
    $srch_str = "_1_of_";
    $pos = strpos($filename, $srch_str);
    if( $pos===false ){
      # Not the first time, ditch
      $this->kslog('DEBUG',"createCategoryCsv() - Not first fragment of Product data. Skipping Category Dump.", null, 'knolseed.log');
      return;
    }else{
      # Create proper filename. Cat_yyyymmdd_1_of_1.csv.gz
      $filename = str_replace("Prod", "Cat", $filename);
      $filename = substr($filename, 0, 12);
      if($filename===false){
        $filename = '';
      }else{
        $filename = $filename."_1_of_1.csv.gz";  
      }
      $this->kslog('DEBUG',"createCategoryCsv() - Final filename = ".$filename, null, 'knolseed.log');
    }

    try{

      // CSV file save path
      $path =  Mage::getBaseDir('var')."/";  # if you want to add new folder for csv files just add name of folder in dir with single quote.
      # $filename = str_replace("Prod", "Cat", $filename);

      $ts   = strtotime($createdate);
      $createday = date('Ymd',$ts);
      $metaheaders = "";
      $metaheaders = '# "Category","'.$createday.'","'.$from.'","'.$to.'"'."\n" ;

      if( !trim($filename)) {
        $this->kslog('DEBUG','Category Dump: Filename is blank! ',null,'knolseed.log');
        $filename = "Cat_".date("Ymd")."_1_of_1.csv.gz" ;
        $this->kslog('DEBUG',"Category Dump: Filename is reset to: ".$filename,null,'knolseed.log');
      }
      
      $fp = gzopen($path.$filename,'w9');
      // throw exception if file opening file
      if (! $fp) {            
        throw new Exception("Category Dump: File creation error. Check that the ".$path." folder has WRITE permissions");
      }

      gzwrite($fp,$metaheaders);
      $metaheadersline2 = "";
      $metaheadersline2 = '#= id=\'id\', name=\'name\',child=\'child_id\''."\n" ;
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
      $this->kslog('DEBUG',"Categories = ".print_r($category,true),null,'knolseed.log');

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

          $this->kslog('DEBUG',"Category: ID=".$entity_id.", Name=".$name.", URL=".$url_path, null, 'knolseed.log');
          $this->kslog('DEBUG',"Category: ID=".$entity_id.", Children=".$children.", Parent=".$parent, null, 'knolseed.log');
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
          $this->kslog('DEBUG',"Writing line to file:".$line, null, 'knolseed.log');
          gzwrite($fp, $line);
        }

        Mage::log("Total ".$count." categories found!", null, 'knolseed.log');
        gzclose($fp);

        $filepath = $path.$filename ;
        $actual_file_name = $filename ;
        $observer = new Knolseed_Engage_Model_Observer();
        if( $observer->pushFileToS3($filepath, $actual_file_name, "category", false, $process_id) ) {
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
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::flushAllKfEntries()",null,'knolseed.log');
    
    // remove process entry afetr 8 days
    $removedays = date('Y-m-d', strtotime("-8 day"));

    $this->kslog('DEBUG',"Remove days = ".print_r($removedays,true),null,'knolseed.log');
    
    // delete record collection
    $collection = Mage::getModel('engage/engage')->getCollection();
    $collection->addFieldToFilter('created_at', array('eq' =>$removedays));

    $kfcrondata = $collection->getData();

    $this->kslog('DEBUG',"kf cron data = ".print_r($kfcrondata,true),null,'knolseed.log');

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
    $this->kslog('DEBUG',"Entry Knolseed_Engage_Helper_Data::updatedAttempts()",null,'knolseed.log');

    try{
      // Check if processid exists
      if($processid){
        // Load process & update current process
        $kf_item = Mage::getModel('engage/engage')->load($processid);
        $updatedattempt = $kf_item->getAttempt() + 1;

        $this->kslog('DEBUG',"Updated attempt = ".print_r($updatedattempt,true),null,'knolseed.log');

        $kf_item->setAttempt($updatedattempt);
        $kf_item->save();
      }
    }catch(Exception $e){
      $this->errorAdminNotification('Updateattempts-error','updateattempts',$e->getMessage(),'',true);
    } 
  }


}


