<?php 
	session_start();
	include '../../lib/Tokenized.php';

    use Bkash\Library\Tokenized;
	
	if (!empty($_GET['id'])) {
		
		$agreement_id = $_GET['id'];

		$bKash = new Tokenized("W");
		$response = $bKash->cancelAgreement($agreement_id);
		print_r($response);

		if(isset($response['statusCode']) && $response['statusCode'] != "" && $response['statusCode'] != "0000"){
            $_SESSION['msg'] = "<div class='alert alert-warning'><strong>Agreement cancellation failed!</strong><br>".$response['statusMessage']."</div>";
            header('Location: ../view/bKash/pay.php');
        }
        else if(isset($response['agreementStatus']) && $response['agreementStatus'] == "Cancelled") {
			deleteSelectedIndexReGenJson($agreement_id);
			$_SESSION['msg'] = "<div class='alert alert-warning'>Agreement Cancelled</div>";
			header('Location: ../view/bKash/pay.php');
		}
		else{
			$_SESSION['msg'] = "<div class='alert alert-warning'>Unable to cancel the agreement!</div>";
		}
	}
	else {
		$_SESSION['msg'] = "<div class='alert alert-warning'>Unable to cancel the agreement!</div>";
	}

	function deleteSelectedIndexReGenJson($values) {
		$main_json_file = file_get_contents('../model/agreements.json', true);
		$decoded_data = json_decode($main_json_file, true);

		foreach($decoded_data as $key => $agreement) 
		{
		    if($agreement['agreementID'] == $values) 
		    {
		    	unset($decoded_data[$key]);
		    	break;
		    }
		}
		$final_json_data = [];
		foreach($decoded_data as $values) {
			$final_json_data[] = $values;
		}

		file_put_contents("../model/agreements.json", json_encode($final_json_data, JSON_PRETTY_PRINT));
	}

?>