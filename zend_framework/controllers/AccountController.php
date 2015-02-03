<?php

class AccountController extends Zend_Controller_Action
{
    public function init()
    {
        $authnetApiInfo = new ZendEx_CreditCard_Authnet_Info();

        $mdlU = new Model_utility();
        $opts = $mdlU->getCPOptions();

        if(!defined('AUTHORIZENET_API_LOGIN_ID')) 
        {
            $authnetApiInfo->setAuthorizenetApiLoginId($opts['apiLoginId']);
            $authnetApiInfo->setAuthorizenetTransactionKey($opts['apiTransKey']);
            if(APPLICATION_ENV == 'production')
                $authnetApiInfo->setSandbox(false);
            else
                $authnetApiInfo->setSandbox(true);
        }

        if(empty($_SESSION['billingProfile'])) $this->_redirect('/Login');

        $this->mdlC = new Model_customer();
        $this->mdlR = new Model_register();

        if(!empty($_SESSION['customers'][$_SESSION['selectedCustomer']]))
            $this->curCust = $this->view->curCust = $_SESSION['customers'][$_SESSION['selectedCustomer']];
        else
            $this->_redirect('/Login');
	}	

	public function indexAction ()
    {
        $this->view-> usageHist = $usageHist = $this->mdlC->getUsageHistory($this->curCust['id']);

        if(isset($usageHist['result']['result']) && count($usageHist['result']['result'])):
            $usageData = array();

            foreach($usageHist['result']['result'] as $usage):
                $usageData[] = array('label' => date('M Y', strtotime($usage['dateFrom'])), 'value' => $usage['amount'], 'color' => '#E87C0D');
            endforeach;

            $this->view->fcData = array('chart' => array('showValues' => '0', 'bgalpha' => '0.0', 'showBorder' => '0',
                'formatNumberScale' => '0', 'numberSuffix' => $this->curCust['unitOfMeasureLbl']),
                'data' => $usageData);
        endif;

        $invoiceRaw = $this->mdlR->getCurrentInvoice($this->curCust['id']);

        if(!empty($invoiceRaw['result']['result'])) $invoice = $invoiceRaw['result']['result'];

        if(!empty($invoice['secureUrl'])) $this->view->secureUrl = $invoice['secureUrl'];
        $this->view->startDate = !empty($invoice['startDate']) ? date('m/d/Y', strtotime($invoice['startDate'])) : 'TBA';
        $this->view->endDate = !empty($invoice['endDate']) ? date('m/d/Y', strtotime($invoice['endDate'])) : 'TBA';
        $this->view->dueDate = !empty($invoice['dueDate']) ? date('m/d/Y', strtotime($invoice['dueDate'])) : 'TBA';
        $this->view->pastDue = !empty($invoice['pastDue']) ? $invoice['pastDue'] : 0;
        $this->view->amountDue = !empty($invoice['amountDue']) ? $invoice['amountDue'] : 0;
        $this->view->renewalDate = !empty($this->curCust['nextRatingGroupStartDate']) ? $this->curCust['nextRatingGroupStartDate'] : 'N/A';

        $lastPayment = $this->mdlC->getLastPayment($this->curCust['id']);

        if($lastPayment['result']['message'] != 'NO PAYMENT HISTORY AVAILABLE'):
            $this->view->lastPaymentDate = date('m/d/Y', strtotime($lastPayment['result']['result'][0]['datePaymentMade']) );
            $this->view->lastPaymentAmt = $lastPayment['result']['result'][0]['amount'];
        endif;

        if(!empty($invoice['billDate'])) $this->view->billDate = date('m/d/Y', strtotime($invoice['billDate']));
        $this->view->charges = !empty($invoice['charges']) ? $invoice['charges'] : 0;
        if(!empty($invoice)) $this->view->totalDue = $invoice['pastDue']+$invoice['amountDue']+$invoice['charges'];
	}
    
    public function infoAction ()
    {
        $this->view->edit = $this->_getParam('edit');
        $invoiceRaw = $this->mdlR->getCurrentInvoice($this->curCust['id']);
        if(!empty($invoiceRaw['result']['result'])) $this->view->invoice = $invoiceRaw['result']['result'];
    }
    
    public function toggleautopayAction()
    {
        $this->set2JSON();
        $cinfo          = new ZendEx_CreditCard_Authnet_Customerinfo();

        if(!empty($_SESSION['billingProfile']['authorizenetCimProfileId']))
        {
            $paymentProfile = $cinfo->getPaymentProfileFromCustProfile($_SESSION['billingProfile']['authorizenetCimProfileId']);

            if(!empty($paymentProfile))
            {
                $paymentType    = array_key_exists('creditCard', $paymentProfile['payment']) ? 'AUTH_CC_AUTO': 'AUTH_ACH_AUTO';
                $data = array(
                    'cimProfileId'      => $_SESSION['billingProfile']['authorizenetCimProfileId'],
                    'billingProfileId'  => $_SESSION['billingProfile']['id'],
                    'cimPaymentId'      => $paymentProfile['customerPaymentProfileId']);

            }

        }

        $billPref = !empty($_SESSION['billingProfile']['billingPreference']) ? $_SESSION['billingProfile']['billingPreference'] : NULL;

         if($billPref == 'AUTH_CC_AUTO' || $billPref == 'AUTH_ACH_AUTO')
         {
            $data['billingPreference'] = NULL;
            //$this->mdlC->wipeAch($this->curCust['id']); ADD ACH
         }   
         else
         {
            if(!empty($paymentType)) $data['billingPreference'] = $paymentType;

            /*if($paymentType == 'AUTH_ACH_AUTO') ADD ACH
            {
                $achInfo = array(
                    'achAccType'       => $paymentProfile['payment']['bankAccount']['accountType'],
                    'routNumber'       => $paymentProfile['payment']['bankAccount']['routingNumber'],
                    'achNameOnAccount' => $paymentProfile['payment']['bankAccount']['nameOnAccount'],
                    'accNumber'        => $paymentProfile['payment']['bankAccount']['accountNumber'],
                    'achBankName'      => $paymentProfile['payment']['bankAccount']['bankName']);
                $this->mdlC->updateAch($this->curCust['id'], $achInfo);
            }
            else
            {
                $this->mdlC->wipeAch($this->curCust['id']);
            }*/
         }

        if(!empty($data))
        {

        }
        $this->mdlC->updateBillingPaymentMethod($data);
        $refreshSession = $this->mdlR->refreshsession($this->curCust['id']);
        $_SESSION       = $refreshSession['result']['result'];
    }

    public function authnetsyncAction()
    {
        $this->set2JSON();
        $mdlC             = new Model_customer();
        $cinfo            = new ZendEx_CreditCard_Authnet_Customerinfo();
        $cimId            = $_SESSION['billingProfile']['authorizenetCimProfileId'];
        $paymentProfile   = $cinfo->getPaymentProfileFromCustProfile($cimId); 
        $paymentTypeInCIM = array_key_exists('creditCard', $paymentProfile['payment']) ? 'AUTH_CC_AUTO': 'AUTH_ACH_AUTO';

        if($paymentTypeInCIM == 'AUTH_CC_AUTO')
        {
            //$mdlC->wipeAch($this->curCust['id']);
        }
        else
        {
            /*$bankAccount = $paymentProfile['payment']['bankAccount'];
            $achInfo = array(
                'achAccType'       => $bankAccount['accountType'],
                'routNumber'       => $bankAccount['routingNumber'],
                'achNameOnAccount' => $bankAccount['nameOnAccount'],
                'accNumber'        => $bankAccount['accountNumber'],
                'achBankName'      => $bankAccount['bankName']);
            $mdlC->updateAch($this->curCust['id'], $achInfo);*/
        }
        
        $data = array(
            'cimProfileId'      => $_SESSION['billingProfile']['authorizenetCimProfileId'],
            'billingPreference' => $paymentTypeInCIM,
            'billingProfileId'  => $_SESSION['billingProfile']['id'],
            'cimPaymentId'      => $paymentProfile['customerPaymentProfileId']);
        
        if(!empty($_SESSION['billingProfile']['authorizenetCimPaymentId']))
        {
            if($_SESSION['billingProfile']['authorizenetCimPaymentId'] != $paymentProfile['customerPaymentProfileId'])
                $cinfo->deleteProfilePaymentMethod($cimId, $_SESSION['billingProfile']['authorizenetCimPaymentId']);
        }
        
        $res = $mdlC->updateBillingPaymentMethod($data);
        $res['result']['paymentProfileId'] = $paymentProfile['customerPaymentProfileId'];
        echo  json_encode($res['result']);   
    }

    /**** ADD ACH ****/
    /*public function onetimepaymentcheckingAction()
    {
        $this->set2JSON();
        $params    = $this->_getAllParams();
        $partialId = $this->curCust['partialId'];
        $aim       = new AuthorizeNetAIM();
        $invoice   = $this->mdlR->getCurrentInvoice($this->curCust['id']);
        
        $aim->setFields(array(
            'amount'         => $params['amount'],
            'method'         => 'echeck',
            'bank_aba_code'  => $params['bank_aba_code_one_time'],
            'bank_acct_num'  => $params['bank_acct_num_one_time'],
            'bank_acct_type' => $params['bank_acct_type_one_time'],
            'bank_name'      => $params['bank_name_one_time'],
            'bank_acct_name' => $params['bank_acct_name_one_time'],
            'echeck_type'    => 'WEB',
            'invoice_num'    => $invoice['result']['0']['id']));

        $response = $aim->authorizeAndCapture();
        $data = array(
                    array(
                        'metaKey'       => 'trigger',
                        'metaValue'     => 'cp-payment-confirmed',
                        'metaValueText' => 'cp-payment-confirmed'),
                    array(
                        'metaKey'       => 'ET_HTML1',
                        'metaValue'     => $response->transaction_id ,
                        'metaValueText' => $response->transaction_id)
                    );
        
        if ($response->response_code == 1)
        {
            $this->mdlC->createCustomerEmailTicket($this->curCust['id'], 'EML-CPORTAL-PAY-CONFIRM', $data);
            $responseArr = array(
                'invoice_number' => $response->invoice_number,
                'amount'         => $response->amount,
                'response'       => $response->response,
                'partialId'      => $partialId);
             
            $this->mdlC->createpaymentrecord($this->curCust['id'], $responseArr);
            echo json_encode(array('successful' => true, 'amount' => $params['amount'], 'confirmationCode' => $response->transaction_id));
        }
        else
        {
            echo json_encode(array('successful' => false));
        }       
    }*/
    
    public function onetimepaymentcreditcardAction()
    {
        $this->set2JSON();
        $params    = $this->_getAllParams();
        $partialId = $this->curCust['partialId'];
        $invoice   = $this->mdlR->getCurrentInvoice($this->curCust['id']);
        $aim       = new AuthorizeNetAIM();
        $aim->setFields(array(
            'amount'      => $params['amount'],
            'card_code'   => $params['card_code_one_time'],
            'card_num'    => $params['card_number_one_time'],
            'exp_date'    => $params['exp_month_one_time'].'-'.$params['exp_year_one_time'],
            'invoice_num' => $invoice['result']['result']['id']));
            
        $response = $aim->authorizeAndCapture();
        
        $data = array(
                    array(
                        'metaKey'       => 'trigger',
                        'metaValue'     => 'cp-payment-confirmed',
                        'metaValueText' => 'cp-payment-confirmed'),
                    array(
                        'metaKey'       => 'ET_HTML1',
                        'metaValue'     => $response->transaction_id ,
                        'metaValueText' => $response->transaction_id)
                    );

        if ($response->response_code == 1)
        {
             $this->mdlC->createCustomerEmailTicket($this->curCust['id'], 'EML-CPORTAL-PAY-CONFIRM', $data);
             $responseArr = array(
                'invoice_number' => $response->invoice_number,
                'amount'         => $response->amount,
                'response'       => $response->response,
                'partialId'      => $partialId
             );
             
            $this->mdlC->createpaymentrecord($this->curCust['id'], $responseArr);
            echo json_encode(array('successful' => true, 'amount' => $params['amount'], 'confirmationCode' => $response->transaction_id));
        }
        else
        {
            echo json_encode(array('successful' => false));
        }        
    }
    
    public function paybillAction() 
    {
        $cinfo                           = new ZendEx_CreditCard_Authnet_Customerinfo();
        //ADD ACH
        if(false):
            $ach                             = $this->mdlC->getCustomerACH($this->curCust['id']);
            $achInfo                         = $this->view->achInfo =  $ach['result']['result'];
            $this->view->bank_acct_type_auto = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['custAchAccType']);
            $this->view->bank_acct_name_auto = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['custAchNameOnAcct']);
            $this->view->bank_name_auto      = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['custAchBankName']);
            $this->view->bank_acct_num_auto  = 'XXXX'.substr( preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['custAchAccNumber']), -4);
            $this->view->bank_aba_code_auto  = 'XXXX'.substr( preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['custAchRoutNumber']), -4);
        endif;

        if(empty($_SESSION['billingProfile']['authorizenetCimProfileId']))
        {
            $cimId          = $cinfo->createProfile('p'.$this->curCust['partialId']);
            $this->mdlC->updateBillingPaymentMethod(array('cimProfileId' => $cimId, 'billingProfileId' => $_SESSION['billingProfile']['id']));
            $refreshSession = $this->mdlR->refreshsession($this->curCust['id']);
            $_SESSION       = $refreshSession['result']['result'];
        }
        
        $invoiceRaw          = $this->mdlR->getCurrentInvoice($this->curCust['id']);
        if(!empty($invoiceRaw['result']['result'])) $invoice = $this->view->invoice = $invoiceRaw['result']['result'];

        $this->view->dueDate     = !empty($invoice['dueDate']) ? date('m/d/Y', strtotime($invoice['dueDate'])) : 'TBA';
        $this->view->pastDue     = !empty($invoice['pastDue']) ? $invoice['pastDue'] : 0;
        $this->view->balance     = !empty($invoice['amountDue']) ? $invoice['amountDue'] : 0;
        $paymentProfile          = $cinfo->getPaymentProfileFromCustProfile($_SESSION['billingProfile']['authorizenetCimProfileId']);
        $this->view->billingPref = $_SESSION['billingProfile']['billingPreference'];

        if(!empty($paymentProfile))
        {
            $this->view->paymentTypeExists = true;
            $this->view->paymentProfileId  = $paymentProfile['customerPaymentProfileId'];
            $this->view->paymentTypeInCIM  = array_key_exists('creditCard', $paymentProfile['payment']) ? 'CC': 'ACH';
            
            if($this->view->paymentTypeInCIM == 'CC')
            {
                if(!empty($paymentProfile['payment']['creditCard']['cardCode']))
                    $this->view->cardNumber = $paymentProfile['payment']['creditCard']['cardNumber'];
                if(!empty($paymentProfile['payment']['creditCard']['expirationDate']))
                    $this->view->expDate    = $paymentProfile['payment']['creditCard']['expirationDate'];
                if(!empty($paymentProfile['payment']['creditCard']['cardCode']))
                    $this->view->cardCode   = $paymentProfile['payment']['creditCard']['cardCode'];
            }
        }
        
        $this->view->token       = $cinfo->getHostedProfilePages($_SESSION['billingProfile']['authorizenetCimProfileId']);
        $this->view->billingPref = $_SESSION['billingProfile']['billingPreference'];
    }
    
    public function billinghistoryAction()
    {
        $invoices             = $this->mdlR->getAllInvoices($this->curCust['id']);
        if(!empty($invoices['result']['result'])) $this->view->invoices = $invoices['result']['result'];
    }
    
    public function billingprofileAction()
    {
        
    }
    
    public function updatepasswordAction()
    {
        $this->set2JSON();
        $params      = $this->_getAllParams();
        $loginStatus = $this->mdlC->login(array('email' => $_SESSION['billingProfile']['email'], 'userpass' => $params['oldPassword']));

        if($loginStatus['message'] != 'PASSWORD MATCH' && !empty($params['oldPassword']))
        {
            echo json_encode(array('successful' => false, 'field' => 'oldPassword'));
        }
        else
        {
            $billingProfileId             = $_SESSION['billingProfile']['id'];
            $this->mdlR->setBillingProfileEmailPassword(array('userpass' => $params['newPassword'],
                'customersBillingProfileId' => $billingProfileId));
            $selectedCust                 = $_SESSION['selectedCustomer'];
            $refreshSession               = $this->mdlR->refreshsession($this->curCust['id']);
            $_SESSION                     = $refreshSession['result']['result'];
            $_SESSION['selectedCustomer'] = $selectedCust;
            echo json_encode(array('successful' => true));
        }
    }
    
    public function updatenotificationinfoAction()
    {
        $this->set2JSON();
        $params                       = $this->_getAllParams();
        $preference                   = array('billMailingPreference' => $params['billingNotify'], 'id' => $_SESSION['billingProfile']['id']);
        $this->mdlC->setPaperlessPreference($preference);
        $selectedCust                 = $_SESSION['selectedCustomer'];
        $refreshSession               = $this->mdlR->refreshsession($this->curCust['id']);
        $_SESSION                     = $refreshSession['result']['result'];
        $_SESSION['selectedCustomer'] = $selectedCust;
        echo json_encode(array('successful' => true));
    }
    
    public function loadaccountAction()
    {
        $this->set2JSON();
        echo json_encode($_SESSION);
    }
    
    public function loadcurrentbillAction()
    {
        $this->set2JSON();
        $mdlR    = new Model_register();
        $invoice = $mdlR->getCurrentInvoice($this->_getParam('accountSelect'));
        if(!empty($invoice['result']['result'])) $invoice['result']['result']['dueDate'] = date('m/d/Y', strtotime($invoice['result']['result']['dueDate']));
        echo json_encode($invoice['result']['result']);
    }
    
    public function loadaccountinvoiceAction()
    {
        $this->set2JSON();
        $acctNum = $this->_getParam('accountSelect');
        $mdlR = new Model_register();
        $invoiceRaw = $mdlR->getCurrentInvoice($acctNum);
        $invoice = $invoiceRaw['result']['result'];
       
        if(!empty($invoice['startDate']))
            $invoice['startDate']   = date('m/d/Y', strtotime($invoice['startDate']));

        if(!empty($invoice['endDate']))
            $invoice['endDate']     = date('m/d/Y', strtotime($invoice['endDate']));

        if(!empty($invoice['dueDate']))
            $invoice['dueDate']     = date('m/d/Y', strtotime($invoice['dueDate']));

        if(!empty($this->curCust['nextRatingGroupStartDate']))
            $invoice['renewalDate'] = date('m/d/Y', strtotime($this->curCust['nextRatingGroupStartDate']));

       $lastPayment = $this->mdlC->getLastPayment($this->curCust['id']);

       if($lastPayment['result']['message'] != 'NO PAYMENT HISTORY AVAILABLE')
       {
            $invoice['lastPaymentReceived']    = date('m/d/Y', strtotime($lastPayment['result']['result'][0]['datePaymentMade']) );
            $invoice['lastPaymentReceivedAmt'] = $lastPayment['result']['result']['amount'];
       }
       
       $invoice['billDate'] = date('m/d/Y', strtotime($invoice['billDate']));
       $invoice['totalDue'] = $invoice['pastDue']+$invoice['amountDue']+$invoice['charges'];
       
       $usageHist = $this->mdlC->getUsageHistory($this->curCust['id']);
       $fcData    = array();
       
        if (isset($usageHist['result']['result']) && count($usageHist['result']['result'])): 
            $usageData = array();
            $chartData = array();
            $chartData['showValues'] = '0';
            $chartData['bgalpha'] = '0,0';
            $chartData['showBorder'] = '0';
            $chartData['formatNumberScale'] = '0';
            $chartData['numberSuffix'] = $this->curCust['unitOfMeasure'];
            foreach($usageHist['result']['result'] as $usage): 
                $data = array();
                $data['label'] = date('M Y', strtotime($usage['dateFrom']));
                $data['value'] = $usage['amount'];
                $data['color'] = '#E87C0D';
                $usageData[] = $data;
            endforeach;
            $fcData['chart'] = $chartData;
            $fcData['data'] = $usageData;
        endif;                       
        
        echo json_encode(array('invoice' => $invoice, 'fcData' => $fcData));
    }
    
    public function updatemailingaddressinfoAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        header('Content-Type: application/json');
        $params = $this->_getAllParams();
        $mdlR = new Model_register();
        $customerInfo = array(
            'mailingAddressLine1' => $params['mailingAddressLine1'],
            'mailingAddressLine2' => $params['mailingAddressLine2'],
            'mailingAddressCity' => $params['mailingCity'],
            'mailingAddressState' => $params['mailingState'],
            'mailingAddressZip'  => $params['mailingZip'],
            'id'                 => $this->curCust['id']
        );
        
        $mdlR->setMailingAddress($customerInfo);
        $selectedCust = $_SESSION['selectedCustomer'];
        $refreshSession = $mdlR->refreshsession($this->curCust['id']);
        $_SESSION = $refreshSession['result']['result'];
        $_SESSION['selectedCustomer'] = $selectedCust;
        echo json_encode(array('successful' => true));
    }
    
    public function updatecontactinfoAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        header('Content-Type: application/json');
        $params = $this->_getAllParams();
        
        $contactInfo = array(
            'phoneHome' => $params['contactInfoHomePhone'],
            'phoneMobile' => $params['contactInfoMobilePhone'],
            'phoneWork'  => $params['contactInfoWorkPhone'],
            'email' => $params['contactInfoEmail'],
            'id' => $_SESSION['billingProfile']['id']);

        $mdlC = new Model_customer();
        $mdlR = new Model_register();
        $mdlC->setContactInfo($contactInfo);
        $mdlR->updateSpouse($_SESSION['billingProfile']['id'], $params['contactSpouse']);
        $refreshSession = $mdlR->refreshsession($this->curCust['id']);
        $selectedCust = $_SESSION['selectedCustomer'];
        $_SESSION = $refreshSession['result']['result'];
        $_SESSION['selectedCustomer'] = $selectedCust;
        echo json_encode(array('successful' => true));
    }
    
    public function updatebillingaddressinfoAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        header('Content-Type: application/json');
        $params = $this->_getAllParams();
        $mdlR = new Model_register();
        $customerInfo = array(
            'billingAddressLine1' => $params['billingAddressLine1'],
            'billingAddressLine2' => $params['billingAddressLine2'],
            'billingCity' => $params['billingCity'],
            'billingState' => $params['billingState'],
            'billingZip'         => $params['billingZip'],
            'id'                 => $this->curCust['id']
        );
    
        $mdlR->setBillingAddress($customerInfo);
        $refreshSession = $mdlR->refreshsession($this->curCust['id']);
        $selectedCust = $_SESSION['selectedCustomer'];
        $_SESSION = $refreshSession['result']['result'];
        $_SESSION['selectedCustomer'] = $selectedCust;
        echo json_encode(array('successful' => true));
    }

    public function switchaccountAction()
    {
        $_SESSION['selectedCustomer'] = $this->_getParam('selectedCustomer');
        $referer = $_SERVER['HTTP_REFERER'];
        $this->_redirect(substr($referer, strpos($referer, '.com/') + strlen('.com/')));
    }

    public function set2JSON()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        header('Content-Type: application/json');
    }

    public function updateautopayinfoAction()
    {
        $this->set2JSON();
        $cimId = $_SESSION['billingProfile']['authorizenetCimProfileId'];
        $cinfo = new ZendEx_CreditCard_Authnet_Customerinfo();
        $params = $this->_getAllParams();
        $mdlR = new Model_register();

        $localPaymentData = $data = array('cimProfileId' => $_SESSION['billingProfile']['authorizenetCimProfileId'],
            'billingPreference' => $params['autoPaymentMethod'],'billingProfileId'  => $_SESSION['billingProfile']['id'],
            'cimPaymentId' => $_SESSION['billingProfile']['authorizenetCimProfileId']);

        if($params['editOrView'] == 'view' || empty($params['autoPaymentMethod']))
        {
            $this->mdlC->updateBillingPaymentMethod($localPaymentData);

            if($params['autoPaymentMethod'] == 'AUTH_CC_AUTO'):
                $refreshSession = $mdlR->refreshsession($this->curCust['id']);
                $_SESSION       = $refreshSession['result']['result'];
                echo json_encode(array('successful' => true, cardNumber => 'XXXX'.substr($params['card_number_auto'], -4)));
            elseif($params['autoPaymentMethod'] == 'AUTH_ACH_AUTO'):
                $achInfo = $this->mdlC->getCustomerACH($this->curCust['id']);
                $achInfo['custAchAccNumber'] = 'XXXX'.substr(preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['result']['result']['custAchAccNumber']),-4);
                $achInfo['custAchRoutNumber'] = 'XXXX'.substr(preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['result']['result']['custAchRoutNumber']),-4);
                $achInfo['custAchAccType'] = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['result']['result']['custAchAccType']);
                $achInfo['custAchBankName'] = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['result']['result']['custAchBankName']);
                $achInfo['custAchNameOnAcct'] = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $achInfo['result']['result']['custAchNameOnAcct']);
                $refreshSession = $mdlR->refreshsession($this->curCust['id']);
                $_SESSION       = $refreshSession['result']['result'];
                echo json_encode(array('successful' => true, 'achInfo' => $achInfo));
            else:
                $refreshSession = $mdlR->refreshsession($this->curCust['id']);
                $_SESSION       = $refreshSession['result']['result'];
                echo json_encode(array('successful' => true, ));
            endif;
                return;
        }
        /**** ADD ACH ****/
        //else if($this->_getParam('autoPaymentMethod') == 'AUTH_CC_AUTO')
        else
        {
            $paymentProfile = new AuthorizeNetPaymentProfile();
            $paymentProfile->billTo->firstName = $_SESSION['billingProfile']['firstName'];
            $paymentProfile->billTo->lastName = $_SESSION['billingProfile']['lastName'];
            $paymentProfile->billTo->address = $_SESSION['billingProfile']['billingAddressLine1'];
            $paymentProfile->billTo->city = $_SESSION['billingProfile']['billingCity'];
            $paymentProfile->billTo->state = $_SESSION['billingProfile']['billingState'];
            $paymentProfile->billTo->zip = $_SESSION['billingProfile']['billingZip'];
            $paymentProfile->billTo->country = 'USA';
            $paymentProfile->payment->creditCard->cardCode = $params['card_code_auto'];
            $paymentProfile->payment->creditCard->cardNumber = $this->_getParam('card_number_auto');
            $paymentProfile->payment->creditCard->expirationDate = $params['exp_year_auto'] == 'XX' ? 'XXXX' : $params['exp_year_auto'].'-'. $params['exp_month_auto'];

            $curPaymentProfile = $cinfo->getPaymentProfileFromCustProfile($cimId);

            if(!empty($curPaymentProfile))
            {
                $cinfo->updatePaymentProfile($cimId, $curPaymentProfile['customerPaymentProfileId'], $paymentProfile);
                $res = $cinfo->getLastResponse();

                if($res->xml->messages->resultCode == 'Ok')
                {
                    $this->mdlC->updateBillingPaymentMethod($localPaymentData);
                    $refreshSession = $mdlR->refreshsession($this->curCust['id']);
                    $_SESSION       = $refreshSession['result']['result'];
                    echo json_encode(array('successful' => true, 'cardNumber' => 'XXXX'.substr($params['card_number_auto'], -4)));
                    return;
                }
                else
                {
                    echo json_encode(array('successful' => false));
                    return;
                }
            }
            else
            {
                $res = $cinfo->createPaymentProfile($_SESSION['billingProfile']['authorizenetCimProfileId'], $paymentProfile);

                if($res['result']['successful'])
                {
                    $localPaymentData['cimPaymentId'] = $res->xml->customerPAymentProfileId;

                    $this->mdlC->updateBillingPaymentMethod($localPaymentData);
                    $refreshSession = $mdlR->refreshsession($this->curCust['id']);
                    $_SESSION       = $refreshSession['result']['result'];
                    echo json_encode(array('successful' => true, 'cardNumber' => 'XXXX'.substr($params['card_number_auto'], -4)));
                }
                else
                {
                    echo json_encode(array('successful' => false));
                }

            }
            return;
        }
    }

    public function paymentsubmittedAction()
    {
        $this->view->amount = $this->_getParam('amount');
        $this->view->confirmationCode = $this->_getParam('confirmationCode');
    }

    public function prodswitchreviewAction()
    {
        $curCust = $this->view->curCust =  $this->curCust;
        $params = $this->view->params = $this->_getAllParams();
        $mdlP = new Model_providers();
        $planId = ($params['type'] == 'fixed') ? $params['planId'] : 'null';
        $this->view->terms = $this->view->terms = $mdlP->getTerms($params['providerId'], $planId);
        $this->view->termsList = ' ';



        foreach($this->view->terms['result'] as $term) {
            $this->view->termsList .= ($term['url'] . ' ');
        }

        $this->view->accountNum = $curCust['state'] = 'GA' ? $curCust['id'] : $curCust['ldcAccountNum'];

        if ($params['providerState'] == 'IL') {
            $this->view->termsList .= ('/Account.comedloa?fname=' . $curCust['firstName'] . '&lname=' . $curCust['lastName'] . '&company=' . $curCust['companyName'] . '&address=' . $curCust['address1'] . '&address2=' . $curCust['address2'] . '&city=' . $curCust['city'] . '&state=' . $curCust['state']  . '&zip=' . $curCust['postalCode'] . '&account_nr=' . $this->view->accountNum . '&rate=' . $this->params['rate'] . '&date=' . date('m/d/Y') . ' ');
        }
    }

    public function comedloaAction()
    {

    }

    public function switchproductAction()
    {
        $this->view->params = $params = $this->_getAllParams();
    }

    public function prodswitchresultAction()
    {
        $successful = $this->_getParam('successful');

        if($successful == true)
        {
            $this->view->header1Message = 'Product Switch Successful';
            $this->view->header2Message = 'A ticket has been submitted for processing';
        }
        else
        {
            $this->view->header1Message = 'An Error occurred while trying to switch your product';
        }

    }
}
