<?php
class ControllerExtensionPaymentUpay extends Controller {
	public function index() {
		$data['button_confirm'] = $this->language->get('button_confirm');

		$test_mode_attr = false;
		if ($this->config->get('payment_upay_test')) {
			$data['action'] = 'https://api.upayments.com/test-payment';
			$test_mode_attr = true;
		}
		else {
			$data['action'] = 'https://api.upayments.com/payment-request';
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$data['FormApiKey'] = password_hash($this->config->get('payment_upay_apikey'),PASSWORD_BCRYPT);

		$data['APIKey'] = $this->config->get('payment_upay_apikey');

		// $data['MerchantID'] = $this->config->get('payment_upay_merchantid');

		// $data['Username'] = $this->config->get('payment_upay_username');
		// $data['Password'] = $this->config->get('payment_upay_password');
		$data['test_mode'] = $test_mode_attr;

		$data['itemprice1'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$data['itemname1'] ='Order ID - '.$order_info['order_id'];
		$data['currencycode'] = $order_info['currency_code'];
		$data['ordid'] = $order_info['order_id'];

		$data['cstemail'] = $order_info['email'];
		$data['cstname'] = html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8');
		$data['cstmobile'] = $order_info['telephone'];
		$data['cntry'] = $order_info['payment_iso_code_2'];

		$data['returnurl'] = $this->url->link('extension/payment/upay/callback', 'hashcd=' . md5($order_info['order_id'] . $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) . $order_info['currency_code'] . $this->config->get('upay_password')));

		$data['confirmURL'] = $this->url->link('extension/payment/upay/confirm');

		return $this->load->view('extension/payment/upay', $data);
	}

	public function callback() {
		$this->load->language('payment/upay');
		$this->log->write('Result new '. $this->request->get['result']);
		$this->load->language('extension/payment/upay');
		$order_id=0;
		$order_id = $this->request->get['opencart_order_id'];
        $payment_id = "";
        $pos = strpos($order_id, "?payment_id");
        if ($pos !== false)
        {
            $payment_id = substr($order_id, $pos + strlen("?payment_id") + 1);
            $order_id = (int)substr($order_id, 0, $pos);
        }
		$this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

		$transauthorised = false;

		if ($order_info) {

			$this->log->write('Inside Order Info new '. $this->request->get['result']);
			$error = '';

			$refid = $this->request->get['ref'];
			$api_key = $this->config->get('payment_upay_apikey');
			$status = $this->request->get['result'];

			//means not valid record
			$order_done = true;
			if($status == "CAPTURED" || $status == "SUCCESS"){
				$transauthorised = true;
			}else if($status == "CANCELED" || $status == "CANCELLED"){
				$this->model_checkout_order->addOrderHistory($order_id, 7, "Thank you for shopping with us. However, the transaction has been canceled.(Track ID = ".$this->request->get['track_id'].")", true);
				$error = $this->language->get('text_cancel_label');
			}
			else{
				$this->model_checkout_order->addOrderHistory($order_id, 10, "Thank you for shopping with us. However, the transaction has been declined.(Track ID = ".$this->request->get['track_id'].")", true);
				$error = $this->language->get('text_declined');
			}


		} else {
			$error = $this->language->get('text_unable');
		}

		if ($error) {

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_basket'),
				'href' => $this->url->link('checkout/cart')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_checkout'),
				'href' => $this->url->link('checkout/checkout', '', 'SSL')
			);

			if($error == 'Your transaction is cancelled'){
				$data['breadcrumbs'][] = array(
					'text' => $this->language->get('text_cancel_header'),
					'href' => $this->url->link('checkout/success')
				);
				$data['heading_title'] = $this->language->get('text_cancel_header');
			}else{
				$data['breadcrumbs'][] = array(
					'text' => $this->language->get('text_failed'),
					'href' => $this->url->link('checkout/success')
				);
				$data['heading_title'] = $this->language->get('text_failed');
			}


			if($error == "text_cancel")
				$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));
			else
				$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));

			$data['button_continue'] = $this->language->get('button_continue');

			$data['continue'] = $this->url->link('common/home');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/success')) {
				$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/common/success', $data));
			} else {
				$this->response->setOutput($this->load->view('common/success', $data));
			}

		} else {
			$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_upay_order_status_id'), "Thank you for shopping with us. Your account has been charged and your transaction is successful.(Track ID = ".$this->request->get['track_id'].")", true);
			$this->response->redirect($this->url->link('checkout/success'));
		}
	}

	 /**
     * confirm
     *
     * @return json|string
     */
    public function confirm(): void
    {
    	$json = [];
		$this->load->language('extension/payment/upay');
		$this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$unique_order_id = md5($order_info['order_id'] * time());
        $src=str_replace('upay.', '', $this->session->data['payment_method']['code']);
		$customer_unq_token = $this->getCustomerUniqueToken($order_info['telephone']);
		$return_url=$this->url->link('extension/payment/upay/callback','opencart_order_id=' . $order_info["order_id"]);
        $return_url=str_replace('&amp;', '&', $return_url);
        if($src == 'upay'){
        	$src = null;
        }

        $params = json_encode([
                "returnUrl" => $return_url,
                "cancelUrl" => $return_url,
                "notificationUrl" => $return_url,
                "order" =>[
                            "amount" => $order_info['total'],
                            "currency" => $order_info['currency_code'] ,
                            "id" => $unique_order_id,
                          ],
                "reference" => [
                            "id" => "".$order_info['order_id'],
                            ],
                "customer" => [
                            "uniqueId" => $customer_unq_token,
                            "name" => $order_info['firstname']." ".$order_info['lastname'],
                            "email" => $order_info['email'],
                            "mobile" => $order_info['telephone'],
                            ],
                "plugin" => [
                            "src" => "opencart",
                            ],
                "language" => "en",
                "paymentGateway" => ["src" => $src,],
                "tokens" => [
                            "creditCard" => '',
                            "customerUniqueToken" => $customer_unq_token,
                            ],
                "device" => [
                            "browser" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36 OPR/93.0.0.0",
                            "browserDetails" => [
                                            "screenWidth" => "1920",
                                            "screenHeight" => "1080",
                                            "colorDepth" => "24",
                                            "javaEnabled" => "false",
                                            "language" => "en",
                                            "timeZone" => "-180",
                                            "3DSecureChallengeWindowSize" => "500_X_600", ],
                            ],
                ]);
        $curl = curl_init();

		if($this->config->get('payment_upay_test')) {
			$apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
		} else {
			$apiUrl = 'https://apiv2api.upayments.com/api/v1/';
		}

		curl_setopt_array($curl, array(
		  CURLOPT_URL =>  $apiUrl.'charge',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>$params,
		  CURLOPT_HTTPHEADER => array(
		    'Accept: application/json',
		    'Content-Type: application/json',
		    'Authorization: Bearer '.$this->config->get('payment_upay_apikey')
		  ),
		));

		$response = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($response, true);
		if($result){
			if (isset($result["errors"])) {
			$json['error'] = "Error from UPayments: ".$result["message"];
			} elseif (isset($result["message"]) && (!isset($result["status"]) || $result["status"] == false)){
			$json['error'] = "Error from UPayments: ".$result["message"];
			}
			if (!$json){
			$json['redirect']=$result["data"]["link"];
			}
		} else {
			$json['error'] = "Error from UPayments: Your IP is not whiltelisted";
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));

    }



    public function cancel():void {
		$this->load->language('extension/payment/upay');

		$order_id=$this->request->get['oc_order_id'];
		$status=$this->request->get['status'];
		$this->load->model('checkout/order');
		if($status == "CANCELED" || $status == "CANCELLED"){
			$this->model_checkout_order->addOrderHistory($order_id, 7, "Thank you for shopping with us. However, the transaction has been canceled.", true);
			$error = $this->language->get('text_cancel_label');
		}
		else{
			$this->model_checkout_order->addOrderHistory($order_id, 10, "Thank you for shopping with us. However, the transaction has been declined.", true);
			$error = $this->language->get('text_declined');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', 'SSL')
		);

		if($error == 'Your transaction is cancelled'){
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_cancel_header'),

			);
			$data['heading_title'] = $this->language->get('text_cancel_header');
		}else{
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_failed'),

			);
			$data['heading_title'] = $this->language->get('text_failed');
		}


		if($error == "text_cancel")
			$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));
		else
			$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));

		$data['button_continue'] = $this->language->get('button_continue');

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/success')) {
			$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/common/success', $data));
		} else {
			$this->response->setOutput($this->load->view('common/success', $data));
		}
    }

	public function getCustomerUniqueToken($phone): string {
		$api_key =  $this->config->get('payment_upay_apikey');
		$token = '';
		if (!empty($phone))
		{
			$phone = str_replace(' ', '', $phone); // Replaces all spaces with hyphens.
            $phone = preg_replace('/[^A-Za-z0-9\-]/','',$phone);
            if (substr($phone, 0, 1) === '0') {
                $phone = '1' . substr($phone, 1);
            }

			$token = $phone;
			$params = json_encode(["customerUniqueToken" => $token, ]);

			$curl = curl_init();

			if($this->config->get('payment_upay_test')) {
				$apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
			} else {
				$apiUrl = 'https://apiv2api.upayments.com/api/v1/';
			}

			curl_setopt_array($curl, [CURLOPT_URL => $apiUrl.'create-customer-unique-token' , CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $params, CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json", "Authorization: Bearer " . $api_key, ], ]);

			$response = curl_exec($curl);
			if ($response)
			{
				$result = json_decode($response, true);
				if (isset($result["status"]) && $result["status"] == true)
				{
					$token = $token;

				}
			}
		}
		return $token;
	}
}