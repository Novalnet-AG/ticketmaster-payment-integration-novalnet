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
  * Script : novalnetbarzahlen.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetBarzahlen extends JPlugin {
	public $id = 'novalnetbarzahlen';

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references.  This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 */
    function plgRDmediaNovalnetBarzahlen( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
        $utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetbarzahlen', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

        $this->test_mode = $this->params->def('test_mode');
		$this->barzahlen_buyer_notification = $this->params->def('barzahlen_buyer_notification');
		$this->slip_due_date = $this->params->def('slip_due_date');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '59';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_barzahlen';
        $this->paygate_url='https://payport.novalnet.de/paygate.jsp';
    }

    /**
     * Plugin method with the same name as the event will be called automatically.
     * You have to get at least a function called display, and the name of the processor
     * Now you should be able to display and process transactions.
     *
    */
    function display() {
        $utilityObject = new novalnetUtilities;
        $db = JFactory::getDBO();
        ## Loading the CSS file.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetbarzahlen/rdmedia_novalnetbarzahlen/css/novalnetbarzahlen.css' );
        $session = JFactory::getSession();
        $this->novalnet_request = $utilityObject->formPaymentParameters('novalnetbarzahlen',$this);

		## Check if this is Joomla 2.5 or 3.0.+
		$isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

		## This will only be used if you use Joomla 2.5 with bootstrap enabled.
		## Please do not change!

		if(!$isJ30){
			if($config->load_bootstrap == 1){
				$isJ30 = true;
			}
		}
		if($utilityObject->doValidation($this)){
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_barzahlen') {
				$novalnetConfigurations = $utilityObject->configrationDetails();
                if($this->layout == 1 && $isJ30 == true ){
					if($novalnetConfigurations->payment_logo == '1'){
                      $barzahlenForm = '<img src="plugins/rdmedia/novalnetbarzahlen/rdmedia_novalnetbarzahlen/images/novalnet_barzahlen.png" />';
					}
                    if($this->test_mode == '1'){
                        $barzahlenForm .= "<div style='color:red;'>".JText::_('PLG_NOVALNETBARZAHLEN_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $barzahlenForm .='<form action="" method="post" name="novalnetForm">';
                    $barzahlenForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" name="enter_barzahlen">'.JText::_( 'PLG_NOVALNETBARZAHLEN_BTN_PAY' ).'</button>';
                    $barzahlenForm .=  '</form>';
                }else{
                    $barzahlenForm = '<form action="" method="post" name="novalnetForm">';
                    $barzahlenForm .= '<div id="plg_rdmedia_novalnetbarzahlen">';
                    $barzahlenForm .= '<div id="plg_rdmedia_novalnetbarzahlen_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_NAME_WT_NOVALNET');
                    $barzahlenForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_DESC')."</span>";
					$barzahlenForm .= "<br/><span>".$this->barzahlen_buyer_notification."</span>";
                    if($this->test_mode == '1'){
                        $barzahlenForm .= "<br /><span style='color:red;'>" . JText::_('PLG_NOVALNETBARZAHLEN_FRONTEND_TESTMODE_DESC') . "</span>";
                    }
                    $barzahlenForm .= '</div>';
                    $barzahlenForm .= '<div id="plg_rdmedia_novalnetbarzahlen_confirmbutton">';
                    $barzahlenForm .= '<input type="submit" name="enter_barzahlen" value="" class="novalnetbarzahlen_button" alt="'.JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETBARZAHLEN_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $barzahlenForm .= '</div>';
                    $barzahlenForm .= '</div>';
                    $barzahlenForm .= '</form>';
                }
                echo $barzahlenForm;
            }
			if(isset($_REQUEST['enter_barzahlen']) && empty($this->novalnet_response['tid'])){				
				$response = $utilityObject->performHttpsRequest($this->paygate_url, http_build_query($this->novalnet_request));
				parse_str($response, $this->novalnet_response);				
				$session->set("novalnet_response_novalnetbarzahlen", $this->novalnet_response);
				if($this->novalnet_response['status'] == 100){
					$response = urlencode($this->novalnet_response);
					header( "Location: ". $this->return_url);
				}else{
					header( "Location: ". $this->cancel_url);
				}
			}
        return true;
	  }
    }

    /**
     * Unsuccessful order
     * @param none
     * @return none
     */
    function novalnetbarzahlen_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
		$utilityObject->prepareTransactionComments('novalnetbarzahlen',$this->novalnet_response,$this);
        $this->clearSession();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetbarzahlen() {
		$utilityObject = new novalnetUtilities;
		$utilityObject->novalnetTransactionSuccess('novalnetbarzahlen',$this);
        $this->clearSession();
    }
    /**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession(){

        $session = JFactory::getSession();
        $session->clear('novalnet_response_novalnetbarzahlen');
        $session->clear('novalnet_request_barzahlen');
        $session->clear('ordercode');
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
