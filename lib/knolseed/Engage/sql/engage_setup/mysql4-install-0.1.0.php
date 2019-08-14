<?php
/**
 * @package     Knolseed
 * @author      Knolseed (product@knolseed.com)
 * @support     support@knolseed.com
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 */
$installer = $this;
$installer->startSetup();
$installer->run("
	   CREATE TABLE IF NOT EXISTS `kf_cron_process` (
	  `process_id` int(11) NOT NULL AUTO_INCREMENT,
	  `date_start` datetime NOT NULL,
	  `date_end` datetime NOT NULL,
	  `file_pushed` enum('0','1') NOT NULL DEFAULT '0',
	  `filename` varchar(30) NOT NULL DEFAULT '0',
	  `type` enum('customer','product') NOT NULL,
	  `attempt` tinyint(4) NOT NULL DEFAULT '0',
	  `created_at` date NOT NULL,
	  PRIMARY KEY (`process_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


  ");
$installer->endSetup();

$installer = $this;
