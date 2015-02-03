<?php

class RegisterController extends Zend_Controller_Action {
	
	public function init()
    {
        if($this->_getParam('logout')) session_unset();
        if(!empty($_SESSION['billingProfile'])) $this->_redirect('/Account');
	}	

	public function indexAction()
    {
        $this->view->header1Message = "Time to take control.";
		$this->view->header2Message = "Just enter your account details.";
		$mdlProviders               = new Model_providers();
		$this->view->providerSelect = $mdlProviders->getProvidersSelectList('styled-select', 'providers', 'providers');
        $this->view->field          = $this->_getParam('field');
        $providerZoneIdPairs = $mdlProviders->getProviderIdZonePairs();
        $this->view->providerZoneIdPairs = $providerZoneIdPairs['result'];
        $this->view->providerRuleInfo = $mdlProviders->getProviderRules();
	}

    public function createprofileAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $params         = $this->_getAllParams();
        $mdlR           = new Model_register();
        unset($params['controller']);
        unset($params['action']);
        unset($params['module']);
        $mdlP           = new Model_providers();
        $providerIdCall = $mdlP->getProviderIdByZone($params['providers']);
        $providerId     = $providerIdCall['result'];

        if($providerId == 46)
        {
            $billingAddr = $mdlR->getBillingAddress($params['account_num1']);
            $serviceAddr =  $mdlR->getServiceAddress($params['account_num1']);
        }
        else
        {
            $processedLdcCall = $mdlP->getProcessedAccountNum($providerId, $params['account_num1'], $params['account_num2']);
            $processedLDC     = $processedLdcCall['result'];
            $mdlR             = new Model_register();
            $billingAddr      = $mdlR->getBillingAddressByLDC($processedLDC);
            $serviceAddr      = $mdlR->getServiceAddressByLDC($processedLDC);
        }

        $_SESSION['registerForm'] = $params;


        $address1 = preg_replace('/[^a-z0-9]+/', '', strtolower($params['streetAddr']));
        $address2 = substr(strtolower($params['streetAddr']), 0, 4);
        $address3 = explode(' ', strtolower($params['streetAddr']));
        $address3 = $address3[0];

        if(empty($billingAddr['result'])):
            $this->_redirect('/Register/index/field/account_num1');
        elseif($providerId != $serviceAddr['result']['providerId']):
            $this->_redirect('/Register/index/field/providers');
        //IF AGL AND E-MAIL DOESN'T MATCH WHAT WE HAVE ON RECORD RETURN FALSE. IF NOT AGL, THEY CAN USE WHATEVER E-MAIL THEY WANT
        elseif(strtolower($params['email']) != strtolower($billingAddr['result']['email']) && empty($processedLDC)):
            $this->_redirect('/Register/index/field/email');
        elseif(
            stristr($address1, strtolower($serviceAddr['result']['address1'])) != FALSE ||
            stristr($address2, strtolower($serviceAddr['result']['address1'])) != FALSE ||
            stristr($address3, strtolower($serviceAddr['result']['address1'])) != FALSE):
            $this->_redirect('/Register/index/field/streetAddr');
        endif;

        session_unset();


        $getProfile = $mdlR->getProfileByEmail($params['email']);

        if((empty($getProfile['result'])))
        {
            $mdlR = new Model_register();
            $mdlR->createBillingProfileFromCustomer($params['email'], $params['password'],
                $billingAddr['result']['firstName'], $billingAddr['result']['lastName'],
                $billingAddr['result']['masterId'], $billingAddr['result']['cid']);
        }
        else
        {
            $mdlR->setBillingProfileEmailPassword(array('userpass' => $params['password'],
                'customersBillingProfileId' => $billingAddr['result']['customersBillingProfileId']));
        }

        $id = !empty($processedLDC) ? $billingAddr['result']['cid'] : $params['account_num1'];
        $this->_redirect('/Register/confirmemail/email/'.$params['email'].'/id/'. $id);

    }
    
    public function confirmemailAction()
    {
        $this->view->email          = $this->_getParam('email');
        $this->view->header1Message = "Almost Done!";
		$this->view->header2Message = "You only need to confirm your email.";
        $params                     = $this->_getAllParams();
        $mdlC                       = new Model_customer();
        $verificationUrl            = $mdlC->createVerificationURL($params['id']);
        $url                        = $this->view->serverUrl().$this->view->baseUrl().
        $this->view->url(array('controller'=>'Register','action'=>'emailverification','confirmCode'=>$verificationUrl), null, true);
        $data = array(
            array(
                'metaKey'       => 'trigger',
                'metaValue'     => 'req-confirmation-cportal',                
                'metaValueText' => 'req-confirmation-cportal'),
            array(
                'metaKey'       => 'ET_HTML1',
                'metaValue'     =>  $url,                
                'metaValueText' => $url));

        $mdlC->createCustomerEmailTicket($params['id'], 'EML-CONFIRM-CPORTAL', $data);
	}
    
    public function emailverificationAction()
    {
        $params   = $this->_getAllParams();
        $decoded  = base64_decode($params['confirmCode']);
        $parsed   = explode('|', $decoded);
        $mdlR     = new Model_register();
        $custInfo = $mdlR->getBillingAddress($parsed[1]);

        if($parsed[0] > strtotime('-1 hour')):
            $mdlR->validate_account($custInfo['result']['customersBillingProfileId']);
            $this->view->header1Message = "Your email has been confirmed.";
            $this->view->link = "<a class='btn-green' href='/Login'>Log In </a>";
        else:
            $this->view->header1Message = "Your email Confirmation has expired.";
            $this->view->link           = "<a style='width: auto' class='btn-green' href='/Register/confirmemail/id/" . $parsed[1]
                . "'>Resend Confirmation Email</a>";
        endif;
    }
    
    public function newpasswordAction()
    {
        session_unset();
        $params                       = $this->_getAllParams();
        $decoded                      = base64_decode($params['confirmCode']);
        $parsed                       = explode('|', $decoded);
        $mdlR                         = new Model_register();
        $custInfo                     = $mdlR->getBillingAddress($parsed[1]);
        $_SESSION['billingProfileId'] = $custInfo['result']['customersBillingProfileId'];
        $refreshSession               = $mdlR->refreshsession($parsed[1]);
        $_SESSION                     = $refreshSession['result']['result'];
        
        if($parsed[0] > strtotime('-1 hour')):
            $this->view->header1Message = "Please enter your new password.";
        else:
            $this->view->header1Message = "Your password reset has expired.";
            $this->view->link = "<a class='btn-green' href='/Login/resetpassword/email/'" . $custInfo['result']['email'] . "'>Resend Temporary Password</a>";
        endif;
    }
    
    public function notverifiedAction()
    {
        $this->view->header1Message = "Your email has not yet been verified";
		$this->view->header2Message = "RESEND EMAIL CONFIRMATION.";
    }
    
    public function emailinstructionsAction()
    {
        $this->view->header1Message = "Thank You";
		$this->view->header2Message = "Please Check your e-mail for further instructions.";
    }
}