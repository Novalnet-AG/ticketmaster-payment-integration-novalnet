<?php

 /**
  * Novalnet payment method module
  * This module is used for real time processing of
  * Novalnet transaction of customers.
  *
  * Copyright (c) Novalnet AG
  *
  * Released under the GNU General Public License
  * This free contribution made by request.
  * If you have found this script useful a small
  * recommendation as well as a comment on merchant form
  * would be greatly appreciated.
  *
  * Script : novalnetpayment.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');

class plgRDmediaNovalnetPayment extends JPlugin {
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for
     * plugins because func_get_args ( void ) returns a copy of all passed arguments
     * NOT references.  This causes problems with cross-referencing necessary for the
     * observer design pattern.
     */
    function plgRDmediaNovalnetPayment( &$subject, $params ) {
        parent::__construct( $subject , $params  );
    }
}
