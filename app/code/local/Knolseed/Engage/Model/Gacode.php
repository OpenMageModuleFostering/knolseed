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
    	Mage::log('Entry Knolseed_Engage_Model_Gacode::toOptionArray', null, 'knolseed.log');

        return 
            "(function (w, d, a, m) {
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
  
    }

}
