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
  * Script : novalnetcc.php
  *
  */

## no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

## Import library dependencies
jimport('joomla.plugin.plugin');
include_once( JPATH_PLUGINS . '/rdmedia/novalnetpayment/helpers/novalnetUtilities.php' );

class plgRDmediaNovalnetCc extends JPlugin {
	public $id = 'novalnetcc';
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for
     * plugins because func_get_args ( void ) returns a copy of all passed arguments
     * NOT references.  This causes problems with cross-referencing necessary for the
     * observer design pattern.
     */
    function plgRDmediaNovalnetCc( &$subject, $params ) {
        parent::__construct( $subject , $params );

        ## Loading language:
        $lang = JFactory::getLanguage();
        $utilityObject = new novalnetUtilities;
        $lang->load('plg_rdmedia_novalnetcc', JPATH_ADMINISTRATOR);
        // To get common configuration parameter
		$utilityObject->getCommonvalues($this);
        $this->test_mode = $this->params->def('test_mode');
        $this->amex_logo = $this->params->def('amex_logo');
        $this->cartasi_logo = $this->params->def('cartasi_logo');
        $this->maestro_logo = $this->params->def('maestro_logo');
        $this->ccsecure_enable = $this->params->def('cc3d_enable');
		$this->cc_buyer_notification = trim($this->params->def('cc_buyer_notification'));
        $this->layout = $this->params->def('layout');
        $this->success_tpl = $this->params->def('success_tpl');
        $this->failure_tpl = $this->params->def('failure_tpl');
        $this->currency = $this->params->def('currency');
        $this->trans_reference_1 = trim($this->params->def('trans_reference_1'));
        $this->trans_reference_2 = trim($this->params->def('trans_reference_2'));
        $this->key = 6;
        $this->novalnet_request  = array();
        $this->novalnet_response = array();
        $this->payment_type = 'novalnet_cc';
        $this->payport_url='https://payport.novalnet.de/pci_payport';
    }

    /**
     * Plugin method with the same name as the event will be called automatically.
     * You have to get at least a function called display, and the name of the processor
     * Now you should be able to display and process transactions.
     *
     */
    function display() {
        $utilityObject = new novalnetUtilities;
        ## Check if this is Joomla 2.5 or 3.0.+

        $isJ30 = version_compare(JVERSION, '3.0.0', 'ge');

        ## This will only be used if you use Joomla 2.5 with bootstrap enabled.
        ## Please do not change!
        if(!$isJ30) {
            if($config->load_bootstrap == 1) {
                $isJ30 = true;
            }
        }
        if($utilityObject->doValidation($this)) {
			$novalnetConfigurations = $utilityObject->configrationDetails();
            if(empty($this->novalnet_response['tid']) && $this->payment_type == 'novalnet_cc') {
				$ccForm ='';
                if($this->layout == 1 && $isJ30) {
				   if($novalnetConfigurations->payment_logo == '1'){
						$ccForm .= '<img src="plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/images/novalnet_cc_visa.png" /><img src="plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/images/novalnet_cc_master.png" />';
						if($this->amex_logo) {
							$ccForm .= '<img src="plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/images/novalnet_amex.png" />';
						}
						if($this->cartasi_logo) {
							$ccForm .= '<img src="plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/images/novalnet_cartasi.png" />';
						}
						if($this->maestro_logo) {
							$ccForm .= '<img src="plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/images/novalnet_maestro.png" />';
						}
					}
					if($this->test_mode == '1'){
                        $ccForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETCC_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $ccForm .= '<form action="'.$this->form_url.'" method="post" name="novalnetForm">';
                    foreach($this->novalnet_request as $key => $value) {
                        $ccForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $ccForm .= '<button class="btn btn-block btn-success" alt="'.JText::_('PLG_NOVALNETCC_PAYMENT_NAME').'" title="'.JText::_('PLG_NOVALNETCC_PAYMENT_NAME').'" style="margin-top: 8px;" type="submit" id="enter_cc" name="enter_cc">'.JText::_( 'PLG_NOVALNETCC_BTN_PAY' ).'</button>';
                    $ccForm .=  '</form>';

                } 	else {
                    $ccForm  = '<form action="'.$this->form_url.'" method="post" name="novalnetForm">';
                    $ccForm .= '<div id="plg_rdmedia_novalnetcc">';
                    $ccForm .= '<div id="plg_rdmedia_novalnetcc_cards"><span style="font-size: 14px">'.JText::_('PLG_NOVALNETCC_PAYMENT_NAME');
                    $ccForm .= "</span><br /><br /><span>".JText::_('PLG_NOVALNETCC_PAYMENT_DESC')."</span><br/><span>".JText::_('PLG_NOVALNETCC_REDIRECT_DESC')."</span>";
                    $ccForm .= '<span>'.$this->buyer_notification.'</span>';
                    if($this->test_mode == '1'){
                        $ccForm .= "<br /><span style='color:red;'>".JText::_('PLG_NOVALNETCC_FRONTEND_TESTMODE_DESC')."</span>";
                    }
                    $ccForm .= '</div>';
                    foreach($this->novalnet_request as $key => $value) {
                        $ccForm .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
                    }
                    $ccForm .= '<div id="plg_rdmedia_novalnetcc_confirmbutton">';
                    $ccForm .= '<input type="submit" id="enter_cc" name="enter_cc" value="" class="novalnetcc_button" alt="'.JText::_('PLG_NOVALNETCC_PAYMENT_NAME').'" title="'.JText::_('PLG_NOVALNETCC_PAYMENT_NAME').'" style="width: 116px;">';
                    $ccForm .= '</div></div></form>';
                }
                echo $ccForm . '<link rel="stylesheet" media="all" href="'.JURI::base(true).'/plugins/rdmedia/novalnetcc/rdmedia_novalnetcc/css/novalnetcc.css">';
            }
            return true;
        }
    }

    /**
     * Unsuccessful order
     * @param none
     * @return none
     */
    function novalnetcc_failed() {
		$utilityObject = new novalnetUtilities;
        $this->novalnet_response = $_REQUEST;
		$utilityObject->prepareTransactionComments('novalnetcc',$this->novalnet_response,$this);
        $this->clearSession();
        $utilityObject->redirectUrl();
    }

    /**
     * Prepare credit card params
     * @param none
     * @return none
     */
    function novalnetcc_form() {
        $session = JFactory::getSession();
        $db = JFactory::getDBO();
        $utilityObject = new novalnetUtilities;
        $this->novalnet_request = $utilityObject->formPaymentParameters('novalnetcc', $this);
        if(isset($_REQUEST['enter_cc'])) {
            $session->set("novalnet_request_cc", $_POST);
        }
        if(isset($_REQUEST['enter_cc']) && empty($this->novalnet_response['tid'])) {
            $form = '<form action="'.$this->payport_url.'" method="post" name="novalnetCcForm" target="novalnetCcIframe">';
            foreach($this->novalnet_request as $key => $value) {
                $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
            }
            $form .= '</form><iframe id="novalnetCcIframe" name="novalnetCcIframe" style="width:100%; height:460px; border:none;"></iframe>';
            $form .= '<script>document.forms.novalnetCcForm.submit();</script>';
            echo $form;
        }
    }

    /**
     * Success the payment
     * @param none
     * @return none
     */
    function novalnetcc() {
		$session = JFactory::getSession();
        $db = JFactory::getDBO();
		$utilityObject = new novalnetUtilities;
		$this->novalnet_response = $_REQUEST;
		$session->set("novalnet_response_novalnetcc", $this->novalnet_response);
        $utilityObject->novalnetTransactionSuccess('novalnetcc',$this);
		$this->clearSession();
    }

    /**
     * Clear session values
     * @param none
     * @return none
     */
    function clearSession() {

        $session = JFactory::getSession();
        $session->clear('ordercode');
        $session->clear('novalnet_request_cc');
        $session->clear('novalnet_response_novalnetcc');
        $session->clear('coupon');
        if(isset($_SESSION['nn']))
            unset($_SESSION['nn']);
    }
}
