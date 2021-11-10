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
  * Script : novalnetpaypal.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php');

class plgRDmediaNovalnetPaypal extends JPlugin
{
	public $id = "novalnetpaypal";
/**
 * Constructor
 *
 * For php4 compatability we must not use the __constructor as a constructor for
 * plugins because func_get_args ( void ) returns a copy of all passed arguments
 * NOT references.  This causes problems with cross-referencing necessary for the
 * observer design pattern.
 */
    function plgRDmediaNovalnetPaypal( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetpaypal', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

        $this->test_mode = $this->params->def('test_mode');
		$this->paypal_buyer_notification = trim($this->params->def('paypal_buyer_notification'));
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '34';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_paypal';
        $this->payport_url='https://payport.novalnet.de/paypal_payport';
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
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetpaypal/rdmedia_novalnetpaypal/css/novalnetpaypal.css' );

        ## Check if this is Joomla 2.5 or 3.0.+
        $isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

        ## This will only be used if you use Joomla 2.5 with bootstrap enabled.
        ## Please do not change!
        if(!$isJ30){
            if($config->load_bootstrap == 1){
                $isJ30 = true;
            }
        }
        if($utilityObject->doValidation($this)) {
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_paypal') {
				$novalnetConfigurations = $utilityObject->configrationDetails();
				$this->novalnet_request = $utilityObject->formPaymentParameters('novalnetpaypal',$this);
                if($this->layout == 1 && $isJ30 == true ) {
					if($novalnetConfigurations->payment_logo == '1'){
						$paypalFrom = '<img src="plugins/rdmedia/novalnetpaypal/rdmedia_novalnetpaypal/images/novalnet_paypal.png" />';
					}
                    if($this->test_mode == '1'){
                        $paypalFrom .= "<div style='color:red;'>".JText::_('PLG_NOVALNETPAYPAL_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $paypalFrom .= '<form action="" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $k => $v) {
                        $paypalFrom .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
                    }
                    $paypalFrom .= '<button class="btn btn-block btn-success" style="margin-top: 8px;" type="submit" id="enter_paypal" name="enter_paypal">'.JText::_( 'PLG_NOVALNETPAYPAL_BTN_PAY' ).'</button>';
                    $paypalFrom .= '</form>';
                } else {
                    $paypalFrom = '<form action="" method="post" name="novalnetForm">';
                    $paypalFrom .= '<div id="plg_rdmedia_novalnetpaypal">';
                    $paypalFrom .= '<div id="plg_rdmedia_novalnetpaypal_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETPAYPAL_PAYMENT_NAME_WT_NOVALNET');
                    $paypalFrom .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETPAYPAL_PAYMENT_DESC')."</span>";
                    $paypalFrom .= "<span>".$this->paypal_buyer_notification."</span>";
                    $paypalFrom .= "<br /><span>".JText::_('PLG_NOVALNETPAYPAL_REDIRECT_DESC')."</span>";
                    if($this->test_mode == '1'){
                        $paypalFrom .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETPAYPAL_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $paypalFrom .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $paypalFrom .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $paypalFrom .= '<div id="plg_rdmedia_novalnetpaypal_confirmbutton">';
                    $paypalFrom .= '<input type="submit" id="enter_paypal" name="enter_paypal" value="" class="novalnetpaypal_button" alt="'.JText::_('PLG_NOVALNETPAYPAL_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETPAYPAL_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $paypalFrom .= '</div>';
                    $paypalFrom .= '</div>';
                    $paypalFrom .= '</form>';
                }
                echo $paypalFrom;
                if(isset($_REQUEST['enter_paypal']) && empty($this->novalnet_response['tid'])) {

                    $this->novalnet_request = $_POST;
                    $form  ='<label for="redirection_msg">'.JText::_('PLG_NOVALNETPAYPAL_REDIRECT_MESSAGE').'</label>';
                    $form .= '<form action="'.$this->payport_url.'" method="post" name="novalnetPaypalForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $form .= '<input type="submit" id="paypal_form_payment" name="paypal_form_payment" value="'.JText::_('PLG_NOVALNETPAYPAL_REDIRECT_VALUE').'" onClick="document.forms.novalnetPaypalForm.submit();document.getElementById(\'paypal_form_payment\').disabled=\'true\';"/></form>';
                    $form .= '<script>document.forms.novalnetPaypalForm.submit();</script>';
                    echo $form;
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
    function novalnetpaypal_failed(){
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalnetpaypal',$this->novalnet_response,$this);
        $utilityObject->redirectUrl();
        $this->clearSession();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetpaypal() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetpaypal", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetpaypal',$this);
		$this->clearSession();

    }
    /**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession(){

        $session = JFactory::getSession();
        $session->clear('ordercode');
        $session->clear('novalnet_response_novalnetpaypal');
        $session->clear($this->ordercode);
        $session->clear('coupon');
        unset($_SESSION['nn']);
    }
}
