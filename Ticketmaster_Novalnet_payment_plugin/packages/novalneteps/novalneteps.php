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
  * Script : novalneteps.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetEps extends JPlugin
{
	public $id = 'novalneteps';
/**
 * Constructor
 *
 * For php4 compatability we must not use the __constructor as a constructor for
 * plugins because func_get_args ( void ) returns a copy of all passed arguments
 * NOT references.  This causes problems with cross-referencing necessary for the
 * observer design pattern.
 */
 function plgRDmediaNovalnetEps( &$subject, $params  ) {

        parent::__construct( $subject , $params  );
		$utilityObject = new novalnetUtilities;
        ## Loading language:
        $lang = JFactory::getLanguage();
        $lang->load('plg_rdmedia_novalneteps', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

        $this->test_mode = $this->params->def('test_mode');
        $this->eps_buyer_notification = $this->params->def('eps_buyer_notification');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '50';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_eps';
        $this->payport_url='https://payport.novalnet.de/giropay';
	}

    /**
     * Plugin method with the same name as the event will be called automatically.
     * You have to get at least a function called display, and the name of the processor
     * Now you should be able to display and process transactions.
     *
     */
     function display()  {

        $db = JFactory::getDBO();
        $utilityObject = new novalnetUtilities;
        ## Loading the CSS file for eps plugin.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalneteps/rdmedia_novalneteps/css/novalneteps.css' );

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
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_eps'){
				$this->novalnet_request = $utilityObject->formPaymentParameters('novalneteps',$this);
				$novalnetConfigurations = $utilityObject->configrationDetails();
                if($this->layout == 1 && $isJ30 == true ) {
					$epsForm = '';
					if($novalnetConfigurations->payment_logo == '1'){
						$epsForm .= '<img src="plugins/rdmedia/novalneteps/rdmedia_novalneteps/images/novalnet_eps.png" />';
					}
                    if($this->test_mode == '1') {
                        $epsForm .="<div style='color:red;'>".JText::_('PLG_NOVALNETEPS_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $epsForm .= '<form action="" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $epsForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }

                    $epsForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETEPS_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETEPS_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" id="enter_eps" name="enter_eps">'.JText::_( 'PLG_NOVALNETEPS_BTN_PAY' ).'</button>';
                    $epsForm .=    '</form>';
                } else {
                    $epsForm = '<form action="" method="post" name="novalnetForm">';
                    $epsForm .= '<div id="plg_rdmedia_novalneteps">';
                    $epsForm .= '<div id="plg_rdmedia_novalneteps_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETEPS_PAYMENT_NAME_WT_NOVALNET');
                    $epsForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETEPS_PAYMENT_DESC')."</span>";
                    $epsForm .= "<br /><span>".JText::_('PLG_NOVALNETEPS_REDIRECT_DESC')."</span>";
					$epsForm .= "<span>".$this->eps_buyer_notification."</span>";
                    if($this->test_mode == '1') {
                        $epsForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETEPS_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $epsForm .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $epsForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $epsForm .= '<div id="plg_rdmedia_novalneteps_confirmbutton">';
                    $epsForm .= '<input type="submit" id="enter_eps" name="enter_eps" value="" class="novalneteps_button" alt="'.JText::_('PLG_NOVALNETEPS_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETEPS_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $epsForm .= '</div>';
                    $epsForm .= '</div>';
                    $epsForm .= '</form>';
                }
                echo $epsForm;
                if(isset($_REQUEST['enter_eps']) && empty($this->novalnet_response['tid'])) {
                    $form = '<label for="redirection_msg">'.JText::_('PLG_NOVALNETEPS_REDIRECT_MESSAGE').'</label>';
                    $form .= '<form action="'.$this->payport_url.'" method="post" name="novalnetepsForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $form .= '<input type="submit" id="eps_form_payment" name="eps_form_payment" value="'.JText::_('PLG_NOVALNETEPS_REDIRECT_VALUE').'" onClick="document.forms.novalnetepsForm.submit();document.getElementById(\'eps_form_payment\').disabled=\'true\';"/></form>';
                    $form .= '<script>document.forms.novalnetepsForm.submit();</script>';
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
    function novalneteps_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalneteps',$this->novalnet_response,$this);
        $this->clearSession();
        $utilityObject->redirectUrl();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalneteps() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalneteps", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalneteps',$this);
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
        $session->clear('novalnet_response_novalneteps');
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
