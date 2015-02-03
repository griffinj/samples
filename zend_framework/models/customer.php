<?php

class Model_customer extends Zend_Db_Table_Abstract {

	protected $_name = 'customer';

    public function init()
    {
        $this->api = new Napower_api ( API_KEY );
    }

	public function login($loginInfo)
    {
        $userInfo = array('userInfo' => $loginInfo);
        $resp = $this->api->call ( SERVICES_URL .'portal','login', $userInfo);
        return $resp['result'];
    }

    public function setContactInfo($contactInfo)
    {
        $contactInfo = array('contactInfo' => $contactInfo);
        $resp        = $this->api->call(SERVICES_URL.'portal','setcontactinformation', $contactInfo);
        return $resp['result'];
    }

    public function setPaperlessPreference($preference)
    {
        $preference  = array('preference' => $preference);
        $resp        = $this->api->call(SERVICES_URL.'portal','setpaperlesspreference', $preference);
        return $resp['result'];
    }

	public function getCustomerACH($id) {
		$customerId = array ('customerId' => $id);
		$resp 		= $this->api->call ( SERVICES_URL . 'portal' , 'getcustomerach', $customerId );
		return $resp;
	}

	public function verifyRepLogin($loginHash) {
		$params     = array('loginInfo' => $loginHash);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'verifyreplogin', $params);
		return $resp;
	}

	public function wipeAch ( $customerId ) {
		$params 	= array('customerId'=>$customerId);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'wipeach', $params);
		return $resp;
	}

	public function updateAch ($customerId, $achInfo) {
		$params  	= array('customerId' => $customerId, 'achInfo' => $achInfo);
		$resp 		= $this->api->call ( SERVICES_URL  . 'portal', 'updateach', $params);
		return $resp;
	}

	public function getLastPayment ($customerId) {
        $params = array('customerId' => $customerId);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'getlastpayment', $params);
		return $resp;
	}

	public function getAllPayments ($customerId) {
		$params		= array ('customerId' => $customerId);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'getallpayments', $params);
		return $resp;

	}

    public function getUsageHistory($customerId)
    {
		$params		= array ('customerId' => $customerId);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'usagehistory', $params);
		return $resp;
    }


    public function updateBillingPaymentMethod($data)
    {
		$params		= array ('data' => $data);
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'updatebillingpaymentmethod', $params);
		return $resp;
    }

	public function createCustomerEmailTicket ($customerId, $data) {
		$params		= array ('customerId' => $customerId, 'data' => $data) ;
		$resp		= $this->api->call ( SERVICES_URL . 'portal', 'createcustomeremailticket', $params );
		return $resp;
	}
	
	public function getUsgHistory($customerId)
    {
		$params  = array ('customerId' => $customerId);
		$resp  = $this->api->call ( SERVICES_URL . 'portal', 'usagehistory', $params);
		return $resp;
    }
    
    public function createVerificationURL($userID)
    {
        $url = base64_encode(time() . '|' . $userID);
        return $url;
    }
    
    public function getCustomerIdFromEmail($email)
    {
        $params = array('email' => $email);
        $resp = $this->api->call(SERVICES_URL . 'portal', 'getcustomerIdfromemail', $params);
        return $resp;
    }
    
    public function createpaymentrecord($customerId, $response)
    {
        $params = array('customerId' => $customerId, 'response' => $response);
        $resp = $this->api->call(SERVICES_URL . 'portal', 'createpaymentrecord', $params);
        return $resp;
    }

    /**
     * Returns all broker fields associated with repId
     * @param int $repId
     * @return array
     */
    public function getCustomerRepData($repId)
    {
	    $params = array('repId' => $repId);
	    $resp = $this->api->call(SERVICES_URL . 'portal', 'getrepdata', $params);
	    return $resp['result'];
    }

    public function  submitChangeRequest($customerId, $planId, $planType, $rate, $numMonths, $terms, $ipAddress, $eSignature)
    {
        $resp = $this->api->call( SERVICES_URL . 'tickets', 'changeproduct',
            array('customerId' => $customerId, 'planId' => $planId, 'planType' => $planType, 'rate' => $rate, 'numMonths' => $numMonths, 'terms' => $terms, 'ipAddress' => $ipAddress, 'eSignature' => $eSignature) );
        return $resp;
    }
	
	public function awLogin($loginInfo)
    {
        $resp = $this->api->call ( SERVICES_URL .'portal','getawcustomerbypassword', $loginInfo);
        return $resp['result'];
	}
	
	public function awLoginFirstTime($loginInfo)
    {
        $resp = $this->api->call ( SERVICES_URL .'portal','updateawpassword', $loginInfo);
        return $resp['result'];
	}

	/**
	 * Gets data for the Free Energy Challenge referral counts
	 * @param int $agentCode
	 * @return array
	 */
	public function getBonuses($agentCode)
	{
		$plans = array();
		
		$sql = "SELECT 
			prig.planGreenPercentage as 'PlanGas',
			prie.planGreenPercentage as 'PlanElectric'
			FROM customers c
			LEFT JOIN customers_ldc cldcg ON (c.id = cldcg.id AND cldcg.commodity = 'Gas')
			LEFT JOIN customers_ldc cldce ON (c.id = cldce.id AND cldce.commodity = 'Electric')
			LEFT JOIN providers_rates_index prig ON prig.id = cldcg.rateIndexId
			LEFT JOIN providers_rates_index prie ON prie.id = cldce.rateIndexId
			LEFT JOIN statuses s ON c.statusId = s.id
			WHERE c.agentCode = $agentCode
			AND c.type = 'Energy'
			AND (s.simpleStatus IN ('ONFLOW', 'PENDFLOW'))";
	
		$allplans = $this->_db->fetchAll($sql);
		
		foreach ($allplans as $plan) {
			if($plan['PlanGas'] && (float)$plan['PlanGas'] > (float)$plans['PlanGas']) {
				$plans['PlanGas'] = (float)$plan['PlanGas'];
			}
			
			if($plan['PlanElectric'] && (float)$plan['PlanElectric'] > (float)$plans['PlanElectric']) {
				$plans['PlanElectric'] = (float)$plan['PlanElectric'];
			}
		}
		
		$sql = "SELECT 
			'ELECTRIC' as `Commodity`,
			SUM(IF(s.simpleStatus = 'ONFLOW', 1, 0)) as 'Active',
			SUM(IF(s.simpleStatus = 'PENDFLOW', 1, 0)) as 'Pending'
			FROM customers c
			LEFT JOIN idstc_distributors i ON c.agentCode = i.RepID
			JOIN customers_ldc cldc ON c.id = cldc.id and cldc.commodity = 'Electric'
			LEFT JOIN statuses s ON c.statusId = s.id
			WHERE (i.EnrollerBCKey = ? OR i.SponsorBCKey = ?)
			AND c.dateCreated >= '2013-08-26 00:00:00'
			AND c.type = 'Energy'
			AND s.simpleStatus IN ('ONFLOW', 'PENDFLOW')
			UNION
			SELECT 
			'GAS' as `Commodity`,
			SUM(IF(s.simpleStatus = 'ONFLOW', 1, 0)) as 'Active',
			SUM(IF(s.simpleStatus = 'PENDFLOW', 1, 0)) as 'Pending'
			FROM customers c
			LEFT JOIN idstc_distributors i ON c.agentCode = i.RepID
			JOIN customers_ldc cldc ON c.id = cldc.id and cldc.commodity = 'Gas'
			LEFT JOIN statuses s ON c.statusId = s.id
			WHERE (i.EnrollerBCKey = ? OR i.SponsorBCKey = ?)
			AND c.dateCreated >= '2013-08-26 00:00:00'
			AND c.type = 'Energy'
			AND s.simpleStatus IN ('ONFLOW', 'PENDFLOW')";
				  
		$commodities = $this->_db->fetchAssoc($sql, array($agentCode,$agentCode,$agentCode,$agentCode));
		
		$ret = array('plans'=>$plans, 'commodities'=>$commodities);
		return $ret;
	}
}
