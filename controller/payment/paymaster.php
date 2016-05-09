<?php
/*--------------------------------------------------------------------------/
* @Author		Hub64 http://www.hub64.com
* @Copyright	Copyright (C) 2014 Hub64.com. All rights reserved.
* @License		Hub64.com Proprietary License
/---------------------------------------------------------------------------*/
class ControllerPaymentPaymaster extends Controller {
	protected function index() {
        $orderId = (int)$this->session->data['order_id'];
        
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($orderId);
        
        $merchant_id = $this->config->get('paymaster_merchant_id');
        
        if(!$merchant_id || $merchant_id == '') {
            exit;
        }
        
        $paymaster_api = 'https://paymaster.ru/Payment/Init?';
        
        $encoded_query = array(
            'invoice'       =>  'invoice',
            'success'       =>  'success',
            'waiting'       =>  'waiting',
            'failed'        =>  'failed'
        );
        
        if(isset($this->request->server['HTTPS']) && $this->request->server['HTTPS'] == 'on') {
            $base = HTTPS_SERVER;
        } else {
            $base = HTTP_SERVER;
        }
        
        $invoiceUrl = $base.'paymaster/'.base64_encode($encoded_query['invoice']);
        $successUrl = $base.'paymaster/'.base64_encode($encoded_query['success']);
        $waitingUrl = $base.'paymaster/'.base64_encode($encoded_query['waiting']);
        $failedUrl  = $base.'paymaster/'.base64_encode($encoded_query['failed']);
        
        /*
        NOTICE: add this RewriteRule... below 'RewriteBase /':
        RewriteRule ^paymaster/([^?]*)$ index.php?route=payment/paymaster/callback&hash=$1 [L]
        */
        
        $order_total = number_format((float)$order_info['total'],2,'.','');
        
        $this->language->load('payment/paymaster');
        
        $api_segments = array(
            'LMI_MERCHANT_ID='.$merchant_id,
            'LMI_PAYMENT_AMOUNT='.$order_total,
            'LMI_CURRENCY='.$order_info['currency_code'],
            'LMI_PAYMENT_NO='.$orderId,
            'LMI_INVOICE_CONFIRMATION_URL='.$invoiceUrl,
            'LMI_PAYMENT_NOTIFICATION_URL='.$waitingUrl,
            'LMI_SUCCESS_URL='.$successUrl,
            'LMI_FAILURE_URL='.$failedUrl,
            'LMI_PAYMENT_DESC='.$this->language->get('text_order').' '.$orderId
        );
        
        $paymaster_api .= implode('&',$api_segments);
        
        $this->data['continue'] = $paymaster_api;
        $this->data['button_confirm'] = $this->language->get('button_confirm');

		if (file_exists(DIR_TEMPLATE.$this->config->get('config_template').'/template/payment/paymaster.tpl')) {
			$this->template = $this->config->get('config_template').'/template/payment/paymaster.tpl';
		} else {
			$this->template = 'default/template/payment/paymaster.tpl';
		}

		$this->render();
	}

	public function callback() {
		$this->load->model('checkout/order');
        
        $response = base64_decode((string)$this->request->get['hash']);
        
        $pm_merchant_id = (string)$this->request->post['LMI_MERCHANT_ID'];
        $pm_order_id    = (int)$this->request->post['LMI_PAYMENT_NO'];
        
        if($response == 'success') {
            $pm_amount      = (float)$this->request->post['LMI_PAYMENT_AMOUNT'];
            $pm_currency    = (string)$this->request->post['LMI_CURRENCY'];
        }
        else {
            $pm_amount      = (float)$this->request->post['LMI_PAID_AMOUNT'];
            $pm_currency    = (string)$this->request->post['LMI_PAID_CURRENCY'];
        }
        
        $order_info = $this->model_checkout_order->getOrder($pm_order_id);
        
        $order_total = (float)number_format((float)$order_info['total'],2,'.','');
        $order_currency = $order_info['currency_code'];
        
        
        
        if($response == '' || !$response || !$order_info || $pm_merchant_id != $this->config->get('paymaster_merchant_id') || $pm_amount < $order_total || $pm_currency != $order_currency) {
            $response = 'failed';
        }
        
        $osi = $this->config->get('paymaster_order_status_id_failed');
        
        if(isset($this->request->server['HTTPS']) && $this->request->server['HTTPS'] == 'on') {
            $this->data['continue'] = HTTPS_SERVER;
        } else {
            $this->data['continue'] = HTTP_SERVER;
        }
        
        if($response != '' && $pm_order_id != '' && $pm_order_id > 0) {
            if($response == 'invoice') { // waiting, user just selected payment method, but not paid yet
                $osi = $this->config->get('paymaster_order_status_id_waiting');
                if($order_info['order_status_id']) {
                    $this->model_checkout_order->update($pm_order_id,$osi,'',FALSE);
                }
                else {
                    $this->model_checkout_order->confirm($pm_order_id,$osi,'',FALSE);
                }
                echo'YES';
                exit;
            }
    
            if($response == 'waiting') { // ok, but not finished yet, wait for success callback
                $osi = $this->config->get('paymaster_order_status_id_waiting');
                if($order_info['order_status_id']) {
                    $this->model_checkout_order->update($pm_order_id,$osi,'',FALSE);
                }
                else {
                    $this->model_checkout_order->confirm($pm_order_id,$osi,'',FALSE);
                }
                $this->data['continue'] = $this->url->link('checkout/success');
            }
            
            if($response == 'success') { // ok, finished, ship products to customer
                $osi = $this->config->get('paymaster_order_status_id_success'); // should be: 5 - completed
                if($order_info['order_status_id']) {
                    $this->model_checkout_order->update($pm_order_id,$osi,'',TRUE);
                }
                else {
                    $this->model_checkout_order->confirm($pm_order_id,$osi,'',TRUE);
                }
                $this->data['continue'] = $this->url->link('checkout/success');
            }
            
            if($response == 'failed') { // failed payment
                $osi = $this->config->get('paymaster_order_status_id_failed');
                if($order_info['order_status_id']) {
                    $this->model_checkout_order->update($pm_order_id,$osi,'',FALSE);
                }
                else {
                    $this->model_checkout_order->confirm($pm_order_id,$osi,'',FALSE);
                }
            }
        }
        
        $this->data['charset']   = $this->language->get('charset');
        $this->data['language']  = $this->language->get('code');
        $this->data['direction'] = $this->language->get('direction');
        
        $this->language->load('payment/paymaster');
        $this->data['title'] = $this->language->get('text_title');
        $this->data['responser'] = $this->language->get('text_'.$response);
        
        if(file_exists(DIR_TEMPLATE.$this->config->get('config_template').'/template/payment/paymaster_callback.tpl')) {
			$this->template = $this->config->get('config_template').'/template/payment/paymaster_callback.tpl';
		} else {
			$this->template = 'default/template/payment/paymaster_callback.tpl';
		}

		$this->response->setOutput($this->render());
	}
}
?>