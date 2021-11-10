<?php
/**
 * Novalnet payment method module
 * This module is used for real time processing of
 * Novalnet transaction of customers.
 *
 * Copyright (c) Novalnet
 *
 * Released under the GNU General Public License
 * This free contribution made by request.
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * Script : novalnet_payment_uninstall.php
 */

define('_JEXEC', 1);
if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(__FILE__));
    require_once JPATH_BASE.'/includes/defines.php';
}
require_once JPATH_BASE.'/includes/framework.php';
	$db = JFactory::getDBO();
	new NovalnetPaymentUninstall();
class NovalnetPaymentUninstall {
    // instance of class
    function __construct()
    {
       $this->startUninstall();
    }
    /**
     * Uninstall Novalnet payments methods
     * @param null
     * @return null
     */
    function startUninstall()
    {
        global $db;
        $db->setQuery("DELETE FROM #__extensions WHERE element like 'novalnet%'");
        $db->query();
        echo 'Novalnet payments uninstalled successfully.';
    }
}
