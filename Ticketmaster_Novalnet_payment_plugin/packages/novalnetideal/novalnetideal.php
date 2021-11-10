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
  * Script : novalnetideal.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php');

class plgRDmediaNovalnetIdeal extends JPlugin
{
	public $id = 'novalnetideal';
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for
     * plugins because func_get_args ( void ) returns a copy of all passed arguments
     * NOT references.  This causes problems with cross-referencing necessary for the
     * observer design pattern.
     */
    function plgRDmediaNovalnetIdeal( &$subject, $params  ) {

        parent::__construct( $subject , $params  );

        ## Loading language:
        $lang = JFactory::getLanguage();
		$utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetideal', JPATH_ADMINISTRATOR);
		$utilityObject->getCommonvalues($this);

		// Assign Paymnet configration details
        $this->test_mode = $this->params->def('test_mode');
        $this->ideal_buyer_notification = $this->params->def('ideal_buyer_notification');
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = '49';
        $this->novalnet_response = array();
        $this->novalnet_request = array();
        $this->payment_type = 'novalnet_ideal';
        $this->payport_url='https://payport.novalnet.de/online_transfer_payport';
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
        ## Loading the CSS file for ideal plugin.
        $document = JFactory::getDocument();
        $document->addStyleSheet( JURI::root(true).'/plugins/rdmedia/novalnetideal/rdmedia_novalnetideal/css/novalnetideal.css' );
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
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_ideal'){
				$this->novalnet_request = $utilityObject->formPaymentParameters('novalnetideal',$this);
				$novalnetConfigurations = $utilityObject->configrationDetails();
                if($this->layout == 1 && $isJ30 == true ) {
					$idealForm = '';
					if($novalnetConfigurations->payment_logo == '1'){
						$idealForm .= '<img src="plugins/rdmedia/novalnetideal/rdmedia_novalnetideal/images/novalnet_ideal.png" />';
					}
                    if($this->test_mode == '1'){
                        $idealForm .="<div style='color:red;'>".JText::_('PLG_NOVALNETIDEAL_FRONTEND_TESTMODE_DESC')."</div>";
                    }
                    $idealForm .= '<form action="" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $idealForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $idealForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETIDEAL_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETIDEAL_PAYMENT_LOGO_TITLE').'" style="margin-top: 8px;" type="submit" id="enter_ideal" name="enter_ideal">'.JText::_( 'PLG_NOVALNETIDEAL_BTN_PAY' ).'</button>';
                    $idealForm .=    '</form>';
                } else {
                    $idealForm = '<form action="" method="post" name="novalnetForm">';
                    $idealForm .= '<div id="plg_rdmedia_novalnetideal">';
                    $idealForm .= '<div id="plg_rdmedia_novalnetideal_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETIDEAL_PAYMENT_NAME_WT_NOVALNET');
                    $idealForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETIDEAL_PAYMENT_DESC')."</span>";
                    $idealForm .= "<br /><span>".JText::_('PLG_NOVALNETIDEAL_REDIRECT_DESC')."</span>";
                    if($this->test_mode == '1'){
                        $idealForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETIDEAL_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $idealForm .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $idealForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $idealForm .= '<div id="plg_rdmedia_novalnetideal_confirmbutton">';
                    $idealForm .= '<input type="submit" id="enter_ideal" name="enter_ideal" value="" class="novalnetideal_button" alt="'.JText::_('PLG_NOVALNETIDEAL_PAYMENT_LOGO_ALT').'" title="'.JText::_('PLG_NOVALNETIDEAL_PAYMENT_LOGO_TITLE').'" style="width: 116px;">';
                    $idealForm .= '</div>';
                    $idealForm .= '</div>';
                    $idealForm .= '</form>';
                }
                echo $idealForm;
                if(isset($_REQUEST['enter_ideal']) && empty($this->novalnet_response['tid'])) {
                    $idealForm = '<label for="redirection_msg">'.JText::_('PLG_NOVALNETIDEAL_REDIRECT_MESSAGE').'</label>';
                    $idealForm .= '<form action="'.$this->payport_url.'" method="post" name="novalnetIdealForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $idealForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $idealForm .= '<input type="submit" id="ideal_form_payment" name="ideal_form_payment" value="'.JText::_('PLG_NOVALNETIDEAL_REDIRECT_VALUE').'" onClick="document.forms.novalnetIdealForm.submit();document.getElementById(\'ideal_form_payment\').disabled=\'true\';"/></form>';
                    $idealForm .= '<script>document.forms.novalnetIdealForm.submit();</script>';
                    echo $idealForm;
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
    function novalnetideal_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
        $utilityObject->prepareTransactionComments('novalnetideal',$this->novalnet_response,$this);
        $this->clearSession();
        $utilityObject->redirectUrl();
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetideal() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetideal", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetideal',$this);
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
        $session->clear('novalnet_response_novalnetideal');
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
