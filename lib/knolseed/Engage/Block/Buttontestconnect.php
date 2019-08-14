<?php 
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Block_Buttontestconnect extends Mage_Adminhtml_Block_System_Config_Form_Field
{

   protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
   {
        Mage::log("Entry Knolseed_Engage_Block_Buttontestconnect::_getElementHtml()",null,'knolseed.log');
        $this->setElement($element); 
        
        $pingURL = $this->getUrl('engage/adminhtml_engage/TestAws_Connection');
        # Mage::log('pingURL='.$pingURL,null,'knolseed.log');

        $awstokenflag = (Mage::getStoreConfig('engage_options/aws/token')) ? '1' : '' ;
        # Mage::log('awstokenflag='.$awstokenflag,null,'knolseed.log');

        $gaflag = (Mage::getStoreConfig('google/analytics/active')) ? '1' : '' ;
        # Mage::log('gaflag='.$gaflag,null,'knolseed.log');

        $gasectionurl = Mage::getModel('adminhtml/url')->getUrl('/system_config/edit/section/google'); //Mage::getBaseUrl()."admin/system_config/edit/section/google/key/".$formkey."/";
        # Mage::log('gasectionurl='.$gasectionurl,null,'knolseed.log');

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
                    ->setType('button')
                    ->setClass('testawsconnection')
                    ->setId('testawsconnection')
                    ->setLabel('Connect')
                    ->setOnClick("testConnection();")
                    ->toHtml();
        
        # Mage::log('Checkpoint 1='.$html,null,'knolseed.log');


        $html .= '<style>  
                button.success {
                    background-color: #46745E;
                    border-color: #46745E;
                    background-image:none;
                }

                button.success:hover {
                    background-color: #46745E;                
                }

                .disabled{
                    background-color: #e6e6e6 !important; 
                    border: 1px solid #AAAAAA;              
                }

                button.fail{
                    background-color: #FCAF81;
                    border-color: #D24403 #A92000 #A92000 #D24403;
                    color: #FFFFFF;
                }
                </style>
                <script type="text/javascript">
                
                 
                 Validation.add("required-entry-firstname", "Please enter a valid Google Account Number.", function(v) {
                                return !Validation.get("IsEmpty").test(v);
                   });
                 
                </script>
                <script type="text/javascript">

                        var tokenvalidate = "'. $awstokenflag .'";
                        var googleanalyticsenabled = "'. $gaflag .'";
                        var coregooglemoduleurl = "'. $gasectionurl .'";
                        function validateToken(){

                            if(tokenvalidate){
                                document.getElementById("engage_options_aws_username").disabled=true;
                                document.getElementById("engage_options_aws_password").disabled=true;
                                document.getElementById("testawsconnection").disabled=true;

                                document.getElementById("engage_options_aws_username").className = "input-text disabled";
                                document.getElementById("engage_options_aws_password").className = "input-text disabled";
                                document.getElementById("testawsconnection").className="disabled";

                                document.getElementById("default-label-credentials").style.display = "none";
                            }
                            
                            document.getElementById("engage_options_google_google_content").disabled=true;
                            document.getElementById("engage_options_google_google_content").className = "textarea disabled";
                            if(googleanalyticsenabled){
                                document.getElementById("gastatus").innerHTML = "KnolSeed Analytics requires Google Analytics (GA) to be enabled.<br> Google Analytics is enabled" ;
                            }else{

                                var anchor = "<div>KnolSeed Analytics requires Google Analytics (GA) to be enabled.<br> Please enable GA <a href="+coregooglemoduleurl+">here</a> and continue KnolSeed configuration.</div>";
                                document.getElementById("gastatus").innerHTML = anchor;
                            }

                        }


                         function reenterCredentials() {
                            document.getElementById("engage_options_aws_username").disabled=false;
                            document.getElementById("engage_options_aws_password").disabled=false;
                            document.getElementById("testawsconnection").disabled=false;

                            document.getElementById("engage_options_aws_username").className = "input-text";
                            document.getElementById("engage_options_aws_password").className = "input-text";
                            document.getElementById("testawsconnection").className = "scalable scalable";
                        }

                        
                        window.onload = function(e){ 
                            validateToken();
                        }


                        function testConnection() {
                            var username=document.getElementById("engage_options_aws_username").value;
                            var password=document.getElementById("engage_options_aws_password").value;
                            params = {
                                email: username,
                                password: password
                            };
                            new Ajax.Request("'.$pingURL.'", {
                                parameters: params,
                                onSuccess: function(response) {
                                    try {
                                        response = response.responseText;
                                        var ele = document.getElementById("testawsconnection");
                                        if (response == 1) {
                                            ele.innerHTML="<span><span><span>Connected</span></span></span>";
                                            ele.classList.add("success");
                                            ele.classList.remove("fail");
                                            document.getElementById("default-label-credentials").style.display = "none";
                                        }
                                        else{
                                            ele.innerHTML="<span><span><span>Connection failed! Try again?</span></span></span>";
                                            ele.classList.add("fail");
                                            ele.classList.remove("success");
                                            document.getElementById("default-label-credentials").style.display = "none";
                                        }
                                    }
                                    catch (e) {
                                        ele.innerHTML="<span><span><span>Connection failed! Try again?</span></span></span>";
                                        ele.classList.add("fail");
                                        ele.classList.remove("success");
                                    }
                                }
                            });
                        } 
                </script>
                ';
                if($awstokenflag){
                    # Mage::log('awstokenflag is true', null, 'knolseed.log');
                    $html .= '<div>Knolseed Plugin is enabled. To change/re-enter credentials,click <a href="javascript:void(0);" onclick="reenterCredentials()">here</a></div>';
                }
        

        # Mage::log('Returning='.$html, null, 'knolseed.log');        
        return $html;
    }
}
?>
