<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
class Knolseed_Engage_Model_Gacode extends Varien_Object
{    
    static public function toOptionArray()
    {
        

        return 
            " var _gaq = [], __bc = [], push = Array.prototype.push;
    		 _gaq.push = function () {
         	 var i = 0, max = arguments.length, arg;
		         while (i < max) {
		           arg = arguments[i++]; push.call(_gaq, arg); push.call(__bc, arg);
		         }
		     };
		     (function() {
		         var bc = document.createElement('script'); bc.type='text/javascript'; bc.async=true;
		         bc.src = 'http://ec2-174-129-103-53.compute-1.amazonaws.com:8080/embed.js';
		         var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(bc, s);
		     })();
		</script>

		<script>
    	__bc.push(['_setCustomerId', 'customer_id']);" ;
            
  
    }
}
