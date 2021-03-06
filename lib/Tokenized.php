<?php 
	namespace Bkash\Library;

	require_once(__DIR__."/BkashAbstract.php");

	class Tokenized extends BkashAbstract
	{
	    protected $secretdata = [];
	    protected $data = [];
	    protected $config = [];
	    protected $pgwmode;

		public function __construct($pgwmode) {

	        $this->config = include(__DIR__.'/../config/bkash.php');
	        date_default_timezone_set('Asia/Dhaka');

	        $this->pgwmode = $pgwmode;
	        $this->setAppkey($this->config['app_key']);
	        $this->setAppsecret($this->config['app_secret']);
	        $this->setUsername($this->config['username']);
	        $this->setPassword($this->config['password']);
	        $this->setCallbackUrl($this->config['callbackUrl']);
	        $this->setAgreementCallbackUrl($this->config['agreementCallbackUrl']);

	        if($this->config['is_sandbox']) {
	        	$this->setEnv($this->config['sandboxBaseUrl']);
	        } else {
	        	$this->setEnv($this->config['liveBaseUrl']);
	        }

	        if($this->pgwmode == 'W') {
	        	$this->setIsAgreement('0001');
	        } else if($this->pgwmode == 'WO') {
	        	$this->setIsAgreement('0011');
	        }

	        if($this->config['is_capture']) {
	        	$this->setCapture('authorization');
	        } else {
	        	$this->setCapture('sale');
	        }
	        $token_api_response = json_decode($this->grantToken(), true);
	        if(!empty($token_api_response['id_token'])) {
	       		$this->setToken($token_api_response['id_token']);
	       	}
	    }

	    public function grantToken() {
	    	$this->secretdata['app_key'] = $this->getAppkey();
	    	$this->secretdata['app_secret'] = $this->getAppsecret();
	    	$this->setApiurl($this->getEnv().$this->config['grantTokenUrl']);

	    	$header = [
				'Content-Type:application/json',
				'password:'.$this->getPassword(),                                                               
		        'username:'.$this->getUsername()                                                          
		    ];	
		    if (!file_exists(__DIR__."/../config/token.json")) {
		    	$response = $this->Post($this->secretdata, $header);
		    	$token_response = json_decode($response, true);

		    	if(isset($token_response['id_token']) && $token_response['id_token'] != "") {

		    		$token_creation_time = date('Y-m-d H:i:s');
		    		$json_token = json_encode(['id_token' => $token_response['id_token'], 'refresh_token' => $token_response['refresh_token'] ,'created_time' => $token_creation_time], JSON_PRETTY_PRINT);

					file_put_contents(__DIR__."/../config/token.json", $json_token);

					if($this->config['is_sandbox']) {
						$logData = [
							"API Title" => "Grant Token API",
							"API URL" => stripslashes(($this->getApiurl())),
							"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
							"Header" => $header,
							"Body" => $this->secretdata,
							"API Response" => json_decode($response, true)
						];
			        	$this->writeLog("Sandbox_API_Response_Log", $logData);
			        }

					return $response;
			    }
			    else {
			    	return ['libMsg' => 'Error in token creation'];
			    }
			}
			else if(file_exists(__DIR__."/../config/token.json")) {
				$previous_token = json_decode(file_get_contents(__DIR__."/../config/token.json"), true);

				$token_creation_time = date('Y-m-d H:i:s');
				$token_start_time = new \DateTime($previous_token['created_time']);
				$token_end_time = $token_start_time->diff(new \DateTime($token_creation_time));

				if($token_end_time->days > 0 || $token_end_time->d > 0 || $token_end_time->h > 0 || $token_end_time->i > 50) 
				{
					$refresh_token_response = json_decode($this->refreshToken($previous_token['refresh_token']), true);
	
					if(isset($refresh_token_response['id_token']) && $refresh_token_response['id_token'] != "") {
						$retoken_creation_time = date('Y-m-d H:i:s');
			    		$rejson_token = json_encode(['id_token' => $refresh_token_response['id_token'], 'refresh_token' => $refresh_token_response['refresh_token'] ,'created_time' => $retoken_creation_time], JSON_PRETTY_PRINT);

						file_put_contents(__DIR__."/../config/token.json", $rejson_token);

						if($this->config['is_sandbox']) {
							$logData = [
								"API Title" => "Refresh Token API",
								"API URL" => stripslashes(($this->getApiurl())),
								"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
								"Header" => $header,
								"Body" => $this->secretdata,
								"API Response" => $refresh_token_response
							];
				        	$this->writeLog("Sandbox_API_Response_Log", $logData);
				        }

						return json_encode($refresh_token_response);
					}
					else if(!empty($refresh_token_response['statusCode']) && $refresh_token_response['statusCode'] != "0000")
					{
						return json_encode($refresh_token_response);
					}
				}
				else {
					return json_encode($previous_token);
				}
			}
	    }

	    public function refreshToken($refresh_token_id) {
	    	$this->secretdata['app_key'] = $this->getAppkey();
	    	$this->secretdata['app_secret'] = $this->getAppsecret();
	    	$this->secretdata['refresh_token'] = $refresh_token_id;
	    	$this->setApiurl($this->getEnv().$this->config['refreshTokenUrl']);

	    	$header = [
				'Content-Type:application/json',
				'password:'.$this->getPassword(),                                                               
		        'username:'.$this->getUsername()                                                          
		    ];	

	    	$response = $this->Post($this->secretdata, $header);
	    	return $response;
	    }

	    public function createAgreement($postdata) {
	    	if($this->pgwmode == 'W') {
		    	$this->readyAgreementParameter($postdata);
		    	$this->setApiurl($this->getEnv().$this->config['createAgreementUrl']);

		    	$header = [ 
			        'Content-Type:application/json',
			        'authorization:'.$this->getToken(),
			        'x-app-key:'.$this->getAppkey()                                                   
			    ];

			    $response = $this->Post($this->data, $header);
			    $status = json_decode($response, true);

			    if($this->config['is_sandbox']) {
					$logData = [
						"API Title" => "Create Agreement API",
						"API URL" => stripslashes(($this->getApiurl())),
						"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
						"Header" => $header,
						"Body" => $this->data,
						"API Response" => json_decode($response, true)
					];
		        	$this->writeLog("Sandbox_API_Response_Log", $logData);
		        }

			    if(isset($status['agreementStatus']) && $status['agreementStatus'] == "Initiated" && isset($status['bkashURL']) && $status['bkashURL'] != "") {
					$this->redirect($status['bkashURL']);
			    } 
			    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
			    	return $status;
			    }
			    else {
			    	return ['libMsg' => 'Unable to create agreement'];
			    }
			}
			else {
		    	return ['libMsg' => 'Unable to create agreement in non agreement mode'];
		    }
	    }

	    public function executeAgreement($payment_id) {
	    	if($this->pgwmode == 'W') {
		    	$this->setApiurl($this->getEnv().$this->config['executeAgreementUrl']);

		    	$header = [ 
			        'Content-Type:application/json',
			        'authorization:'.$this->getToken(),
			        'x-app-key:'.$this->getAppkey()                                                   
			    ];

			    $this->data['paymentID'] = $payment_id;

			    $response = $this->Post($this->data, $header);
			    
				if($response) {
				    $status = json_decode($response, true);

				    if($this->config['is_sandbox']) {
						$logData = [
							"API Title" => "Execute Agreement API",
							"API URL" => stripslashes(($this->getApiurl())),
							"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
							"Header" => $header,
							"Body" => $this->data,
							"API Response" => json_decode($response, true)
						];
			        	$this->writeLog("Sandbox_API_Response_Log", $logData);
			        }

				    if(isset($status['agreementStatus']) && $status['agreementStatus'] != "") {
						return $status;
				    } 
				    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
				    	return $status;
				    }
				    else {
				    	return ['libMsg' => 'Error in execute agreement'];
				    }
				}
				else {
					$response = $this->queryPayment($payment_id);
					$status = json_decode($response, true);

				    if(isset($status['transactionStatus']) && $status['transactionStatus'] != "") {
						return $status;
				    } 
				    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
				    	return $status;
				    }
				    else {
				    	return $status;
				    }
				}
			}
			else {
		    	return ['libMsg' => 'Unable to execute agreement in non agreement mode'];
		    }
	    }

	    public function agreementStatus($agreement_id) {
	    	if($this->pgwmode == 'W') {
		    	$this->setApiurl($this->getEnv().$this->config['queryAgreementUrl']);

		    	$header = [ 
			        'Content-Type:application/json',
			        'authorization:'.$this->getToken(),
			        'x-app-key:'.$this->getAppkey()                                                   
			    ];

			    $this->data['agreementID'] = $agreement_id;

			    $response = $this->Post($this->data, $header);
			    $query_agreement_response = json_decode($response, true);

			    if($response) {
			    	if($this->config['is_sandbox']) {
						$logData = [
							"API Title" => "Query Agreement API",
							"API URL" => stripslashes(($this->getApiurl())),
							"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
							"Header" => $header,
							"Body" => $this->data,
							"API Response" => json_decode($response, true)
						];
			        	$this->writeLog("Sandbox_API_Response_Log", $logData);
			        }

			    	return $query_agreement_response;
			    }
			    else {
			    	return ['libMsg' => 'Error in query agreement'];
			    }
			}
			else {
		    	return ['libMsg' => 'Unable to query agreement in non agreement mode'];
		    }
	    }

	    public function cancelAgreement($agreement_id) {
	    	if($this->pgwmode == 'W') {
		    	$this->setApiurl($this->getEnv().$this->config['cancelAgreementUrl']);

		    	$header = [ 
			        'Content-Type:application/json',
			        'authorization:'.$this->getToken(),
			        'x-app-key:'.$this->getAppkey()                                                   
			    ];

			    $this->data['agreementID'] = $agreement_id;

			    $response = $this->Post($this->data, $header);
			    $cancel_agreement_response = json_decode($response, true);

			    if($response) {
			    	if($this->config['is_sandbox']) {
						$logData = [
							"API Title" => "Cancel Agreement API",
							"API URL" => stripslashes(($this->getApiurl())),
							"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
							"Header" => $header,
							"Body" => $this->data,
							"API Response" => json_decode($response, true)
						];
			        	$this->writeLog("Sandbox_API_Response_Log", $logData);
			        }
			    	return $cancel_agreement_response;
			    }
			    else {
			    	return ['libMsg' => 'Error in cancel agreement'];
			    }
			}
			else {
		    	return ['libMsg' => 'Unable to cancel agreement in non agreement mode'];
		    }
	    }


	    public function createPayment($postdata) {
	    	$this->readyParameter($postdata);
	    	$this->setApiurl($this->getEnv().$this->config['createPaymentUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $response = $this->Post($this->data, $header);
		    $status = json_decode($response, true);

		    if($this->config['is_sandbox']) {
				$logData = [
					"API Title" => "Create Payment API",
					"API URL" => stripslashes(($this->getApiurl())),
					"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
					"Header" => $header,
					"Body" => $this->data,
					"API Response" => json_decode($response, true)
				];
	        	$this->writeLog("Sandbox_API_Response_Log", $logData);
	        }

		    if(isset($status['transactionStatus']) && $status['transactionStatus'] == "Initiated" && isset($status['bkashURL']) && $status['bkashURL'] != "") {
				$this->redirect($status['bkashURL']);
		    } 
		    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
		    	return $status;
		    }
		    else {
		    	return ['libMsg' => 'Unable to create Bkash URL'];
		    }
	    }

	    public function executePayment($payment_id) {
	    	$this->setApiurl($this->getEnv().$this->config['executePaymentUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $this->data['paymentID'] = $payment_id;

		    $response = $this->Post($this->data, $header);
			if($response) {
			    $status = json_decode($response, true);

			    if($this->config['is_sandbox']) {
					$logData = [
						"API Title" => "Execute Payment API",
						"API URL" => stripslashes(($this->getApiurl())),
						"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
						"Header" => $header,
						"Body" => $this->data,
						"API Response" => json_decode($response, true)
					];
		        	$this->writeLog("Sandbox_API_Response_Log", $logData);
		        }

			    if(isset($status['transactionStatus']) && $status['transactionStatus'] != "") {
					return $status;
			    } 
			    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
			    	return $status;
			    }
			    else {
			    	return ['libMsg' => 'Error in execute payment'];
			    }
			}
			else {
				$response = $this->queryPayment($payment_id);
				$status = json_decode($response, true);

			    if(isset($status['transactionStatus']) && $status['transactionStatus'] != "") {
					return $status;
			    } 
			    else if(isset($status['statusCode']) && $status['statusCode'] != "0000") {
			    	return $status;
			    }
			    else {
			    	return $status;
			    }
			}
	    }

	    public function queryPayment($payment_id) {
	    	$this->setApiurl($this->getEnv().$this->config['queryUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $this->data['paymentID'] = $payment_id;

		    $response = $this->Post($this->data, $header);

		    if($response) {
		    	if($this->config['is_sandbox']) {
					$logData = [
						"API Title" => "Query Payment API",
						"API URL" => stripslashes(($this->getApiurl())),
						"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
						"Header" => $header,
						"Body" => $this->data,
						"API Response" => json_decode($response, true)
					];
		        	$this->writeLog("Sandbox_API_Response_Log", $logData);
		        }
		    	return $response;
		    }
		    else {
		    	return ['libMsg' => 'Error in query payment'];
		    }
	    }

	    public function searchTransaction($trxid) {
	    	$this->setApiurl($this->getEnv().$this->config['searchTranUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $this->data['trxID'] = $trxid;

		    $response = $this->Post($this->data, $header);
		    $decoded_response = json_decode($response, true);

		    if($this->config['is_sandbox']) {
				$logData = [
					"API Title" => "Search Transaction API",
					"API URL" => stripslashes(($this->getApiurl())),
					"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
					"Header" => $header,
					"Body" => $this->data,
					"API Response" => json_decode($response, true)
				];
	        	$this->writeLog("Sandbox_API_Response_Log", $logData);
	        }

	    	return $decoded_response;
	    }

	    public function refundTransaction($postdata) {
	    	$this->readyRefundParameter($postdata);
	    	$this->setApiurl($this->getEnv().$this->config['refundUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $response = $this->Post($this->data, $header);
		    $refund_response = json_decode($response, true);

		    if($this->config['is_sandbox']) {
				$logData = [
					"API Title" => "Refund API",
					"API URL" => stripslashes(($this->getApiurl())),
					"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
					"Header" => $header,
					"Body" => $this->data,
					"API Response" => json_decode($response, true)
				];
	        	$this->writeLog("Sandbox_API_Response_Log", $logData);
	        }

		    if((isset($refund_response['transactionStatus']) && $refund_response['transactionStatus'] != "") && (isset($refund_response['originalTrxID']) && $refund_response['originalTrxID'] != "")) {
		    	return $refund_response;
		    }
		    else if(isset($refund_response['statusCode']) && $refund_response['statusCode'] != "0000") {
		    	return $refund_response;
		    }
		    else {
		    	return ['libMsg' => 'Refund API not responding'];
		    }
	    }

	    public function refundStatus($postdata) {
	    	$this->readyRefundStatusParameter($postdata);
	    	$this->setApiurl($this->getEnv().$this->config['refundStatusUrl']);

	    	$header = [ 
		        'Content-Type:application/json',
		        'authorization:'.$this->getToken(),
		        'x-app-key:'.$this->getAppkey()                                                   
		    ];

		    $response = $this->Post($this->data, $header);
		    $refund_query_response = json_decode($response, true);

		    if($this->config['is_sandbox']) {
				$logData = [
					"API Title" => "Refund Status API",
					"API URL" => stripslashes(($this->getApiurl())),
					"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
					"Header" => $header,
					"Body" => $this->data,
					"API Response" => json_decode($response, true)
				];
	        	$this->writeLog("Sandbox_API_Response_Log", $logData);
	        }

		    if(isset($refund_query_response['statusCode']) && $refund_query_response['statusCode'] != "0000") {
		    	return $refund_query_response;
		    }
		    else {
		    	return $refund_query_response;
		    }
	    }

	    public function capturePayment($payment_id) {
	    	if($this->config['is_capture']) {
	    		$this->setApiurl($this->getEnv().$this->config['capturePaymentUrl'].$payment_id);

		    	$header = [ 
			        'Content-Type:application/json',
			        'authorization:'.$this->getToken(),
			        'x-app-key:'.$this->getAppkey()                                                   
			    ];

			    $response = $this->Post("", $header);
			    $status = json_decode($response, true);

			    if($this->config['is_sandbox']) {
					$logData = [
						"API Title" => "Capture Payment API",
						"API URL" => stripslashes(($this->getApiurl())),
						"Request Timestamp" => date('Y-m-d\TH:i:s.Z')."Z",
						"Header" => $header,
						"Body" => $this->data,
						"API Response" => json_decode($response, true)
					];
		        	$this->writeLog("Sandbox_API_Response_Log", $logData);
		        }

			    if(isset($status['transactionStatus']) && $status['transactionStatus'] == "Completed") {
					return $response;
			    } else {
			    	return "Unable to capture payment! Reason: ". $status['statusCode']." - ".$status['errorMessage'];
			    }
	    	} else {
	    		return "Trying to capture payment in sale mode!";
	    	}
	    	
	    }

	    public function readyAgreementParameter(array $param) {
	    	$this->data['mode'] = '0000';
	    	$this->data['payerReference'] = (isset($param['payerReference'])) ? $param['payerReference'] : '01111111111';
	    	$this->data['callbackURL'] = $this->getAgreementCallbackUrl();
	    	$this->data['amount'] = (isset($param['amount'])) ? $param['amount'] : null;
	    	$this->data['currency'] = "BDT";
	    	$this->data['intent'] = $this->getCapture();
	    	$this->data['merchantInvoiceNumber'] = (isset($param['merchantInvoiceNumber'])) ? $param['merchantInvoiceNumber'] : null;

	    	return $this->data;
	    }

	    public function readyParameter(array $param) {
	    	$this->data['mode'] = (isset($param['mode'])) ? $param['mode'] : $this->getIsAgreement();
	    	$this->data['callbackURL'] = $this->getCallbackUrl();
	    	if($this->pgwmode == "W") {
	    		$this->data['payerReference'] = null;
	        	$this->data['agreementID'] = (isset($param['agreementID'])) ? $param['agreementID'] : null;
	        }
	        else {
	        	$this->data['payerReference'] = (isset($param['payerReference'])) ? $param['payerReference'] : '01111111111';
	        }
	    	$this->data['amount'] = (isset($param['amount'])) ? $param['amount'] : null;
	    	$this->data['currency'] = "BDT";
	    	$this->data['intent'] = $this->getCapture();
	    	$this->data['merchantInvoiceNumber'] = (isset($param['merchantInvoiceNumber'])) ? $param['merchantInvoiceNumber'] : null;
	    	$this->data['merchantAssociationInfo'] = (isset($param['merchantAssociationInfo'])) ? $param['merchantAssociationInfo'] : null;

	    	return $this->data;
	    }

	    public function readyRefundParameter(array $param) {
	    	$this->data['paymentID'] = (isset($param['paymentID'])) ? $param['paymentID'] : null;
	    	$this->data['amount'] = (isset($param['amount'])) ? $param['amount'] : null;
	    	$this->data['trxID'] = (isset($param['trxID'])) ? $param['trxID'] : null;
	    	$this->data['sku'] = (isset($param['sku'])) ? $param['sku'] : null;
	    	$this->data['reason'] = (isset($param['reason'])) ? $param['reason'] : null;

	    	return $this->data;
	    }

	    public function readyRefundStatusParameter(array $param) {
	    	$this->data['paymentID'] = (isset($param['paymentID'])) ? $param['paymentID'] : null;
	    	$this->data['trxID'] = (isset($param['trxID'])) ? $param['trxID'] : null;

	    	return $this->data;
	    }
	}