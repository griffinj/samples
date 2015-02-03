<?php

class Model_register extends Zend_Db_Table_Abstract {

	protected $_name = 'register';

    public function init()
    {
        $this->api = new Napower_api ( API_KEY );
    }

	public function validate_account($billingProfileId)
    {
		$params = array ('billingProfileId' => $billingProfileId);
		$resp   = $this->api->call (SERVICES_URL . "portal", "verifyemail", $params);
		return $resp['result'];
	}
	
	public function setBillingProfileEmailPassword($customerInfo)
    {
		$params = array('customerInfo' => $customerInfo);
		$resp = $this->api->call (SERVICES_URL . "portal", "setBillingProfileEmailPassword", $params);
		return $resp['result'];
	}
	
	
	public function check_email($email)
    {
		$params = array('email' => $email);
		$resp = $this->api->call ( SERVICES_URL .'portal','checkemail',
			$params);
		return $resp['result'];
	}

	public function getMailingAddress($customerId)
    {
		$params = array('customerId' => $customerId);
		$resp = $this->api->call ( SERVICES_URL . 'portal','getmailingaddress', $params);
		return $resp['result'];
	}
	
	public function getBillingAddress($customerId)
    {
		$params = array('customerId' => $customerId);
		$resp = $this->api->call ( SERVICES_URL . 'portal','getbillingaddress', $params);
		return $resp['result'];
	}

    public function getBillingAddressByLDC($LDC)
    {
        $params = array('LDC' => $LDC);
        $resp = $this->api->call ( SERVICES_URL . 'portal','getbillingaddressbyldc', $params);
        return $resp['result'];
    }
	
	public function getServiceAddress($customerId)
    {
		$params = array('customerId' => $customerId);
		$resp = $this->api->call ( SERVICES_URL . 'portal','getserviceaddress', $params);
		return $resp['result'];
	}

    public function getServiceAddressByLDC($LDC)
    {
        $params = array('LDC' => $LDC);
        $resp = $this->api->call ( SERVICES_URL . 'portal','getserviceaddressbyldc', $params);
        return $resp['result'];
    }
	
	public function setMailingAddress($customerInfo)
    {
		$params = array('customerInfo' => $customerInfo);
		$resp = $this->api->call ( SERVICES_URL . 'portal','setmailingaddress', $params);
		return $resp['result'];
	}
	
	public function setBillingAddress($customerInfo)
    {
		$params = array('customerInfo' => $customerInfo);
		$resp = $this->api->call ( SERVICES_URL . 'portal','setbillingaddress', $params);
		return $resp['result'];
	}
	
	public function getCurrentInvoice($customerId)
    {
        $params = array('customerId' => $customerId);
		$resp = $this->api->call ( SERVICES_URL . 'portal','getlatestinvoice', $params);
		return $resp;
	}
	
	
	public function getAllInvoices($customerId)
    {
		$params = array('customerId' => $customerId);
		$resp = $this->api->call ( SERVICES_URL . 'portal','getallinvoices', $params);
		return $resp;
	}
	
	public function loginTest($userInfo)
    {
		$api = new Napower_api ( API_KEY );
		$resp = $api->call ( SERVICES_URL . 'portal','login', array('userInfo'=>$userInfo) );
		return $resp;
	}
	
	public function refreshsession($customerId)
    {
		$params = array ('customerId' => $customerId);
		$resp = $this->api->call (SERVICES_URL . 'portal','refreshsession', $params);
		return $resp;
	}
	
	public function getStates()
    {
		$params = array();
		$resp = $this->api->call (SERVICES_URL . 'portal', 'getstates', $params);
		return $resp;
	}
	
	public function updateSpouse($billingProfileId, $spouseName)
    {
		$params = array('billingId' => $billingProfileId, 'spouseName' => $spouseName ) ;
		$resp = $this->api->call (SERVICES_URL . 'portal','updatespousename', $params);
		return $resp;
	}
	
	public function getRates ($providerZoneId)
    {
		$params = array('providerZoneId' => $providerZoneId);
		$resp = $this->api->call ( SERVICES_URL . 'providers', 'getrates', $params) ;
		return $resp;
	}

    public function setpwd($customerInfo)
    {
        $params = array('customerInfo' => $customerInfo);
        $resp = $this->api->call ( SERVICES_URL . 'providers', 'setpwd', $params) ;
        return $resp;
    }

    public function getProfileByEmail($email)
    {
        $params = array('email' => $email);
        $resp = $this->api->call ( SERVICES_URL . 'portal', 'getProfileByEmail', $params) ;
        return $resp;

    }

    public function createBillingProfileFromCustomer($email, $password, $firstName, $lastName,
        $masterId = '', $customerId = '')
    {
        $params =  array('customerInfo' =>  array('email' => $email, 'password' => $password,
            'firstName' => $firstName, 'lastName' => $lastName, 'masterId' => $masterId,
            'customerId' => $customerId));
        $resp = $this->api->call ( SERVICES_URL . 'portal', 'createbillingprofile', $params) ;
        return $resp;
    }
}