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
  * Script : novalnetprzelewy24.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php');

class plgRDmediaNovalnetPrzelewy24 extends JPlugin
{
	public $id = "novalnetprzelewy24";
/**
 * Constructor
 *
 * For php4 compatability we must not use the __constructor as a constructor for
 * plugins because func_get_args ( void ) returns a copy of all passed arguments
 * NOT references.  This causes problems with cross-referencing necessary for the
 * observer design pattern.
 */
    function plgRDmediaNovalnetPrzelewy24( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetprzelewy24', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

        $this->test_mode = $this->params->def('test_mode');
		$this->przelewy24_buyer_notification = trim($this->params->def('przelewy24_buyer_notification'));
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '78';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_przelewy24';
        $this->payport_url='https://payport.novalnet.de/globalbank_transfer';
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
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetprzelewy24/rdmedia_novalnetprzelewy24/css/novalnetprzelewy24.css' );

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
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_przelewy24') {
				$novalnetConfigurations = $utilityObject->configrationDetails();
				$this->novalnet_request = $utilityObject->formPaymentParameters('novalnetprzelewy24',$this);
                if($this->layout == 1 && $isJ30 == true ) {
					if($novalnetConfigurations->payment_logo == '1'){
						$przelewy24From = '<img src="plugins/rdmedia/novalnetprzelewy24/rdmedia_novalnetprzelewy24/images/novalnet_przelewy24.png" />';
					}
                    if($this->test_mode == '1'){
                        $przelewy24From .= "<div style='color:red;'>".JText::_('PLG_NOVALNETPRZELEWY24_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $przelewy24From .= '<form action="" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $k => $v) {
                        $przelewy24From .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
                    }
                    $przelewy24From .= '<button class="btn btn-block btn-success" style="margin-top: 8px;" type="submit" id="enter_przelewy24" name="enter_przelewy24">'.JText::_( 'PLG_NOVALNETPRZELEWY24_BTN_PAY' ).'</button>';
                    $przelewy24From .= '</form>';
                } else {
                    $przelewy24From = '<form action="" method="post" name="novalnetForm">';
                    $przelewy24From .= '<div id="plg_rdmedia_novalnetprzelewy24">';
                    $przelewy24From .= '<div id="plg_rdmedia_novalnetprzelewy24_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETPRZELEWY24_PAYMENT_NAME_WT_NOVALNET');
                    $przelewy24From .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETPRZELEWY24_PAYMENT_DESC')."</span>";
                    $przelewy24From .= "<span>".$this->przelewy24_buyer_notification."</span>";
                    $przelewy24From .= "<br /><span>".JText::_('PLG_NOVALNETPRZELEWY24_REDIRECT_DESC')."</span>";
                    if($this->test_mode == '1'){
                        $przelewy24From .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETPRZELEWY24_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $przelewy24From .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $przelewy24From .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $przelewy24From .= '<div id="plg_rdmedia_novalnetprzelewy24_confirmbutton">';
                    $przelewy24From .= '<input type="submit" id="enter_przelewy24" name="enter_przelewy24" value="" class="novalnetprzelewy24_button" alt="'.JText::_('PLG_NOVALNETPRZELEWY24_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETPRZELEWY24_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $przelewy24From .= '</div>';
                    $przelewy24From .= '</div>';
                    $przelewy24From .= '</form>';
                }
                echo $przelewy24From;
                if(isset($_REQUEST['enter_przelewy24']) && empty($this->novalnet_response['tid'])) {

                    $this->novalnet_request = $_POST;
                    $form  ='<label for="redirection_msg">'.JText::_('PLG_NOVALNETPRZELEWY24_REDIRECT_MESSAGE').'</label>';
                    $form .= '<form action="'.$this->payport_url.'" method="post" name="novalnetPrzelewy24Form">';
                    foreach($this->novalnet_request as $key => $value) {
                        $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $form .= '<input type="submit" id="przelewy24_form_payment" name="przelewy24_form_payment" value="'.JText::_('PLG_NOVALNETPRZELEWY24_REDIRECT_VALUE').'" onClick="document.forms.novalnetprzelewy24Form.submit();document.getElementById(\'przelewy24_form_payment\').disabled=\'true\';"/></form>';
                    $form .= '<script>document.forms.novalnetPrzelewy24Form.submit();</script>';
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
    function novalnetprzelewy24_failed(){
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalnetprzelewy24',$this->novalnet_response,$this);
        $utilityObject->redirectUrl();
        $this->clearSession();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetprzelewy24() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetprzelewy24", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetprzelewy24',$this);
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
        $session->clear('novalnet_response_novalnetprzelewy24');
        $session->clear($this->ordercode);
        $session->clear('coupon');
        unset($_SESSION['nn']);
    }
}
