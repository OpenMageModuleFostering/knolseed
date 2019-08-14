<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
require_once(Mage::getBaseDir()."/lib/knolseed/aws-autoloader.php");

use Aws\S3\S3Client;


class Knolseed_Engage_Model_Observer extends Mage_Core_Model_Abstract
{

  # public $aws_connection = '';
  public $aws_bucketname = '';
  public $aws_foldername = '';
  public $aws_token = '';
  public $aws_acl = 'private';
  public $aws_access_key = null;

  public $kf_authurl = 'http://app.knolseed.com/settings/keys?';
  public $kf_tracks3pushurl = 'http://app.knolseed.com/uploads/register?';

  public function __construct() {
    Mage::log("Entry Knolseed_Engage_Model_Observer::__construct()",null,'knolseed.log');
    date_default_timezone_set( Mage::app()->getStore()->getConfig('general/locale/timezone') );
    
    $this->aws_token = Mage::getStoreConfig('engage_options/aws/token');
    # $this->aws_connection = $this->getAwsAccessKey($this->aws_token);
  }


  /**
   * Get AWS access key & secret key with session token
   *
   * 
   * @param   $token
   * @return  access key & secret key
   */
  public function getAwsAccessKey($token){
    Mage::log("Entry Knolseed_Engage_Model_Observer::getAwsAccessKey()",null,'knolseed.log');

    # if(!is_null($this->aws_access_key)){
      # Mage::log("Returning existing AWS Access Key",null,'knolseed.log');      
      # return $this->aws_access_key;
    # }

    try{
      Mage::log("Generating new AWS Access Key",null,'knolseed.log');      
      $http = new Varien_Http_Adapter_Curl();
      $config = array('timeout' => 15); # Or whatever you like!
      $config['header'] = true;
      $config['ssl_cert'] = false;

      $requestQuery = "auth_token=".$token;

      $http->setConfig($config);
     
      ## make a POST call
      $http->write(Zend_Http_Client::GET, $this->kf_authurl . $requestQuery );

      ## Get Response
      $response = $http->read();
      # Mage::log("Response = ". print_r($response,true),null,'knolseed.log');

      # $modResponse = preg_replace('/(\r\n|\r|\n)/s',"\n",$response);
      # $responseParts = array();
      # $responseParts = explode("\n\n", $modResponse);
      $responseParts = explode("\r\n\r\n", $response);

      # Close Call
      $http->close();

      # Fix for ignoring HTTP Headers in response.
      # Mage::log("Response Header = ". print_r($responseParts[0],true),null,'knolseed.log');
      # Mage::log("Response Body = ". print_r($responseParts[1],true),null,'knolseed.log');
      $accessdetails = json_decode($responseParts[1]);
      # $accessdetails =  json_decode($response);
     
      # Mage::log("JSON Decoded Response = ". print_r($accessdetails,true),null,'knolseed.log');
      # Mage::log('Printing response for '.$this->kf_authurl,null, 'knolseed.log');
      # Mage::log($accessdetails,null, 'knolseed.log');
      if( $accessdetails->data->access_key_id ) {
        # getting access key & secret key
        $accesskey = $accessdetails->data->access_key_id;
        $secretkey = $accessdetails->data->secret_access_key;
        $session_token = $accessdetails->data->session_token;

        $this->aws_bucketname = $accessdetails->data->s3_bucket;
        # $this->aws_bucketname = 'microsoft.com';
        $this->aws_foldername = $accessdetails->data->s3_folder;

        // Establish connection with DreamObjects with an S3 client.
        $client = S3Client::factory(array( 
            'key'    => $accesskey,
            'secret' => $secretkey,
            'token'  => $session_token
        ));

        # Mage::log("JSON Decoded Response = ". print_r($accessdetails,true),null,'knolseed.log');

        $this->aws_access_key = $client;
        return $client;

      }else{
        //Admin notification
        $errormessage = "Error: Product sync unable to contact Knolseed at ".$this->kf_authurl.$requestQuery.". Will retry again later" ;
        Mage::helper('engage')->errorAdminNotification('GetTemporaryCredentials','AWSpush',$errormessage,'',true);
      }

    }catch(Exception $e){
      //Admin notification
      $errormessage = "Critical Error! Product sync unable to contact Knolseed at ".$this->kf_authurl.$requestQuery.". Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      Mage::helper('engage')->errorAdminNotification('GetTemporaryCredentials','AWSpush',$errormessage,'',true);
    } 

  }


  /**
   * Get AWS bucket,folder & token from DB
   *
   * Make a call to Product & Customer CSV creation method
   * 
   */
   public function setScript()
   { 
      Mage::log("Entry Knolseed_Engage_Model_Observer::setScript()",null,'knolseed.log');

      try{      
        date_default_timezone_set( Mage::app()->getStore()->getConfig('general/locale/timezone') );

        # call to product & customer CSV creation method
        Mage::helper('engage')->processCustomerfile();
        Mage::helper('engage')->processProductfile();
        Mage::helper('engage')->processHistoricalData();

        # flush kf_cron_process table
        Mage::helper('engage')->flushAllKfEntries();
      }catch(Exception $e){
        $errormessage = "Error: Product sync unable to contact Knolseed at ". $this->kf_authurl . $requestQuery .". Will retry again later" ;

        Mage::helper('engage')->errorAdminNotification('setScript','AWSpush',$errormessage,'',true);
      }    
  }
  

  public function getAWSFolderName($type){
    Mage::log("Entry Knolseed_Engage_Model_Observer::getAWSFolderName()",null,'knolseed.log');

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
  * 1. Create a Manifest file with specified name & type.
  * 2. Add $filenames into manifest file
  * 3. Upload Manifest file to appropriate S3 folder, and return success
  */
  public function addManifestFile($manifestFileName, $type, $processid, $filenames){
    $baseDir =  Mage::getBaseDir('var');
    $fullPath = $baseDir."/".$manifestFileName;
    $fp = gzopen($fullPath,'w9');
    foreach($filenames as $fn){
      gzwrite($fp, $fn."\n");
    }
    gzclose($fp);

    # Push to S3
    $this->pushFileToS3($fullPath, $manifestFileName, $type, true, $processid);
    unlink($fullPath);
  }



  /**
   * Push created CSV files to S3 Bucket
   * @param   $filepath,$filename
   * @return  push file object to S3 bucket
   */ 
  public function pushFileToS3($filepath, $filename, $type, $is_manifest_file, $processid){
    Mage::log("Entry Knolseed_Engage_Model_Observer::pushFileToS3()",null,'knolseed.log');

    $aws_connection = $this->getAwsAccessKey($this->aws_token);

    $subfolder = $this->getAWSFolderName($type);
    # $key to upload file on S3 bucket
    $key         = $this->aws_foldername.'/'.$subfolder.'/'.$filename;
    # $key         = $this->getAWSFolderName($type).'/'.$filename;
    Mage::log("Uploading to S3, key=".$key, null, "knolseed.log") ;
    $source_file = $filepath;

    # upload file to S3 bucket
    try{      
      $response = $aws_connection->upload($this->aws_bucketname, $key, fopen($source_file, 'r'), $this->aws_acl);
      # $this->trackS3PushToKf($filename,$type);
      if($is_manifest_file === true){
        $this->trackS3PushToKf($filename, $type);        
      }
 
      # unlink($filepath);
      Mage::log("Uploaded. Returning true", null, "knolseed.log") ;

      return true ;

    }catch(Exception $e){
      // Check if critical error or retriable error
      if($processid){
        $kf_item = Mage::getModel('engage/engage')->load($processid);
        $critical = ($kf_item->getAttempt() >= 1) ? true : false ;

        // Update attempt counts for RETRIABLE errors 
        Mage::helper('engage')->updatedAttempts($processid);
      }else{
        $critical = true;
      }

      $errormessage = "Critical Error! ". $type ." sync unable to authenticate with Knolseed. Please email support@knolseed.com about this error, and we will help you to fix this problem." ;
      
      Mage::helper('engage')->errorAdminNotification('pushFileToS3','AWSpush',$errormessage,$filename,$critical);

      Mage::log("Removing file:".$filepath, null, 'knolseed.log');
      unlink($filepath);

      Mage::log("Upload failed! Returning false", null, "knolseed.log") ;
      return false ;
    }

  }


  /**
   * This function get response from S3 bucket for each S3 push.
   * This response is being logged into kf_error.log file.
   */
  public function trackS3PushToKf($filename,$type){
    Mage::log("Entry Knolseed_Engage_Model_Observer::trackS3PushToKf()",null,'knolseed.log');

    try{
      $http = new Varien_Http_Adapter_Curl();
      $config = array('timeout' => 15); # Or whatever you like!
      $config['header'] = false;

      // request url for calling S3 API
      $requestQuery = "auth_token=".$this->aws_token."&file=".$filename."&type=".$type;


      $http->setConfig($config);
     
      ## make a POST call
      $http->write(Zend_Http_Client::GET, $this->kf_tracks3pushurl . $requestQuery );
     
      ## Get Response
      $response = $http->read();
      $data = json_decode($response);
      
      # Close Call
      $http->close(); 
    }catch(Exception $e){
      Mage::helper('engage')->errorAdminNotification('trackS3PushToKf','s3response',$e->getMessage(),$filename,true);
    }

  }


  /**
   * Remove username & password from DB
   */
  public function removeUserPass($evt){
    Mage::log("Entry Knolseed_Engage_Model_Observer::removeUserPass()",null,'knolseed.log');

    $this->saveGoogleAnalyticsCode();

    $coreConfigObj = new Mage_Core_Model_Config();
    $path = "engage_options/aws/username";
    $coreConfigObj ->deleteConfig($path, $scope = 'default', $scopeId = 0);

    $coreConfigObj2 = new Mage_Core_Model_Config();
    $path2 = "engage_options/aws/password";
    $coreConfigObj2 ->deleteConfig($path2, $scope = 'default', $scopeId = 0);

    // Check if Google Analytics is enabled or not
    $gacontent = Mage::getStoreConfig('engage_options/google/google_content');
    $gaacctnumber = Mage::getStoreConfig('engage_options/google/google_account_number');
    

    /*if($gacontent !== null ){

      if( $gaacctnumber == null) {

        
      }

      // Make core google analytics module active
      $coreConfigObj3 = new Mage_Core_Model_Config();
      $coreConfigObj3->saveConfig('google/analytics/active', 1, 'default', 0);

      // Update core google analytics account number
      $coreConfigObj4 = new Mage_Core_Model_Config();
      $coreConfigObj4->saveConfig('google/analytics/account', $gaacctnumber, 'default', 0);

    }*/

  }


  public function setUploadDataTimeframe(){
    Mage::log("Entry Knolseed_Engage_Model_Observer::setUploadDataTimeframe()",null,'knolseed.log');

    date_default_timezone_set( Mage::app()->getStore()->getConfig('general/locale/timezone') );

    // Get current date
    $today = date('Y-m-d');

    $uploadtime = Mage::getStoreConfig('upload_options/upload/time');
    if($uploadtime){
      Mage::log("Txn data upload time = ".print_r($uploadtime, true), null, 'knolseed.log');

      # Create ts for "Today Time", "now" and "Tomorrow Time"
      # If "Today Time" > now() schedule for "Today Time"
      # Else schedule for "Tomorrow Time"
      $now = date("Y-m-d H:i:s");
      $nowInSecs = strtotime($now);
      Mage::log("Now = ".print_r($now, true), null, 'knolseed.log');

      $execTimeToday =  $today." ".$uploadtime;
      $execTimeTodayInSecs = strtotime($execTimeToday);
      Mage::log("execTimeToday = ".print_r($execTimeToday, true), null, 'knolseed.log');

      $coreConfigObj3 = new Mage_Core_Model_Config();
      if($execTimeTodayInSecs > $nowInSecs){
        # Schedule for today
        $coreConfigObj3->saveConfig('upload_options/upload/transaction', $execTimeToday, 'default', 0);
      }else{
        # Schedule for tomorrow
        $execTimeTomorrow = date("Y-m-d H:i", $execTimeTodayInSecs+86400);
        Mage::log("execTimeTomorrow = ".print_r($execTimeTomorrow, true), null, 'knolseed.log');
        $coreConfigObj3->saveConfig('upload_options/upload/transaction', $execTimeTomorrow, 'default', 0);
      }

    }else{
      Mage::log("Txn data upload time is not found", null, 'knolseed.log');
    }

    Mage::app()->getStore()->resetConfig();
  }


  public function saveGoogleAnalyticsCode(){
    Mage::log("Entry Knolseed_Engage_Model_Observer::saveGoogleAnalyticsCode()",null,'knolseed.log');

    $googlecode = "(function (w, d, a, m) {
        w['_knolseed'] = w['_knolseed'] || [];
        /** intercepts ga.js calls */
        var push = Array.prototype.push;
        w['_gaq'] = [];
        w['_gaq'].push = function () {
           var i = 0, max = arguments.length, arg;
           while (i < max) {
             arg = arguments[i++]; push.call(_gaq, arg); push.call(_knolseed, arg);
           }
       };
       /** intercepts analytics.js calls*/
        w['ga'] = function() {
          (w['ga'].q = w['ga'].q || []).push(arguments);
          (w['_knolseed'] = w['_knolseed'] || []).push(arguments);
        };
        a = d.createElement('script'),
        m = d.getElementsByTagName('script')[0];
        a.async = 1;
        a.src = 'http://ers.knolseed.com:1234/embed.js';
        m.parentNode.insertBefore(a, m)
      })(window,document);

    </script>

    <script>
        _knolseed.push([\"_setCustomerId\", \"customer_id\"]);
    ";

    $coreConfig = new Mage_Core_Model_Config();
    $coreConfig->saveConfig('engage_options/google/google_content', $googlecode, 'default', 0);
    Mage::app()->getStore()->resetConfig();

  }

}

?>

