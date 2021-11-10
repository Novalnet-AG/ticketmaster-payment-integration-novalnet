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
  * Script : novalnetprepayment.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetPrepayment extends JPlugin {
	public $id = 'novalnetprepayment';

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for
	 * plugins because func_get_args ( void ) returns a copy of all passed arguments
	 * NOT references.  This causes problems with cross-referencing necessary for the
	 * observer design pattern.
	 */
    function plgRDmediaNovalnetPrepayment( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
        $utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetprepayment', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

        $this->test_mode = $this->params->def('test_mode');
        $this->payment_ref1 = trim($this->params->def('payment_reference_1'));
        $this->payment_ref2 = trim($this->params->def('payment_reference_2'));
        $this->payment_ref3 = trim($this->params->def('payment_reference_3'));
		$this->prepayment_buyer_notification = $this->params->def('prepayment_buyer_notification');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '27';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_prepayment';
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
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetprepayment/rdmedia_novalnetprepayment/css/novalnetprepayment.css' );
        $session = JFactory::getSession();
        $this->novalnet_request = $utilityObject->formPaymentParameters('novalnetprepayment',$this);

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
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_prepayment') {
				$novalnetConfigurations = $utilityObject->configrationDetails();
                if($this->layout == 1 && $isJ30 == true ){
					if($novalnetConfigurations->payment_logo == '1'){
                      $prepaymentForm = '<img src="plugins/rdmedia/novalnetprepayment/rdmedia_novalnetprepayment/images/novalnet_prepayment.png" />';
					}
                    if($this->test_mode == '1'){
                        $prepaymentForm .= "<div style='color:red;'>".JText::_('PLG_NOVALNETPREPAYMENT_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $prepaymentForm .='<form action="" method="post" name="novalnetForm">';
                    $prepaymentForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" name="enter_prepayment">'.JText::_( 'PLG_NOVALNETPREPAYMENT_BTN_PAY' ).'</button>';
                    $prepaymentForm .=  '</form>';
                }else{
                    $prepaymentForm = '<form action="" method="post" name="novalnetForm">';
                    $prepaymentForm .= '<div id="plg_rdmedia_novalnetprepayment">';
                    $prepaymentForm .= '<div id="plg_rdmedia_novalnetprepayment_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_NAME_WT_NOVALNET');
                    $prepaymentForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_DESC')."</span>";
					$prepaymentForm .= "<br/><span>".$this->prepayment_buyer_notification."</span>";
                    if($this->test_mode == '1'){
                        $prepaymentForm .= "<br /><span style='color:red;'>" . JText::_('PLG_NOVALNETPREPAYMENT_FRONTEND_TESTMODE_DESC') . "</span>";
                    }
                    $prepaymentForm .= '</div>';
                    $prepaymentForm .= '<div id="plg_rdmedia_novalnetprepayment_confirmbutton">';
                    $prepaymentForm .= '<input type="submit" name="enter_prepayment" value="" class="novalnetprepayment_button" alt="'.JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETPREPAYMENT_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $prepaymentForm .= '</div>';
                    $prepaymentForm .= '</div>';
                    $prepaymentForm .= '</form>';
                }
                echo $prepaymentForm;
            }
			if(isset($_REQUEST['enter_prepayment']) && empty($this->novalnet_response['tid'])){
				$response = $utilityObject->performHttpsRequest($this->paygate_url, http_build_query($this->novalnet_request));
				parse_str($response, $this->novalnet_response);
				$session->set("novalnet_response_novalnetprepayment", $this->novalnet_response);
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
    function novalnetprepayment_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
		$utilityObject->prepareTransactionComments('novalnetprepayment',$this->novalnet_response,$this);
        $this->clearSession();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetprepayment() {
		$utilityObject = new novalnetUtilities;
		$utilityObject->novalnetTransactionSuccess('novalnetprepayment',$this);
        $this->clearSession();
    }
    /**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession(){

        $session = JFactory::getSession();
        $session->clear('novalnet_response_novalnetprepayment');
        $session->clear('novalnet_request_prepayment');
        $session->clear('ordercode');
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
