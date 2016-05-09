<?php
class ControllerPaymentQiwi extends Controller {
	protected function index() {
    	$this->data['button_confirm'] = $this->language->get('button_confirm');
		$this->data['button_back'] = $this->language->get('button_back');


		$this->data['action'] = 'https://w.qiwi.ru/setInetBill_utf.do';

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);


		$this->load->language('payment/qiwi');
		$this->data['sub_text_info'] = $this->language->get('sub_text_info');
		$this->data['sub_text_info_phone'] = $this->language->get('sub_text_info_phone');

		$this->data['qiwi_limit'] = $this->language->get('qiwi_limit');
		$this->data['button_back'] = $this->language->get('button_back');

            $this->data['description'] = $this->config->get('config_store') . ' ' . $this->language->get('order_id') . $order_info['order_id'];

		// Переменные
		$this->data['from'] = $this->config->get('qiwi_shop_id');
		$this->data['txn_id'] = $this->session->data['order_id'];
		
		$rur_code = $this->config->get('qiwi_rub_select');
		$rur_order_total = $this->currency->convert($order_info['total'], $order_info['currency_code'], $rur_code);
		$this->data['summ'] = $this->currency->format($rur_order_total, $rur_code, $order_info['currency_value'], FALSE);
		$this->data['summ'] = round((float)$this->data['summ'] + (float)$this->data['summ']/100*(float)$this->config->get('qiwi_markup'), 2);

		$this->data['check_agt'] = false;
		$this->data['lifetime'] = $this->config->get('qiwi_lifetime');

		$this->data['phone'] = preg_replace("/\D+/", "", $order_info['telephone']);
		if (strlen ($this->data['phone']) > 10) $this->data['phone'] = substr($this->data['phone'], -10);

		$this->data['return'] = HTTP_SERVER . 'index.php?route=checkout/success';

		$products = $this->cart->getProducts();
		$_prods = "   Заказ: ";
		foreach ($products as $product) 
		{ 
			$_prods = $_prods . $product['name'] . " - " . $product['quantity'] . " шт ";
		} 


		$_prods = $this->data['description'];// . $_prods;
		if (mb_strlen($_prods,'UTF-8')>255) $_prods = mb_substr($_prods, 0, 252, 'UTF-8').'...';

		$this->data['com'] = html_entity_decode($_prods, ENT_QUOTES, 'UTF-8');

		$this->data['markup'] = $this->language->get('markup').$this->data['summ'].$this->language->get('qiwi_rubls'); 

		$this->id = 'payment';

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/qiwi.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/qiwi.tpl';
		} else {
			$this->template = 'default/template/payment/qiwi.tpl';
		}

		$this->render();
	}

	public function confirm() {

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		if(!$order_info) return;

		$order_id = $this->session->data['order_id'];

		if( $order_info['order_status_id'] == 0) {
			$this->model_checkout_order->confirm($order_id, $this->config->get('qiwi_order_status_progress_id'), 'QIWI');
			return;
		}

		if( $order_info['order_status_id'] != $this->config->get('qiwi_order_status_progress_id')) {
			$this->model_checkout_order->update($order_id, $this->config->get('qiwi_order_status_progress_id'),'QIWI',TRUE);
		}

   	}

	public function fail() {

		$this->redirect(HTTP_SERVER . 'index.php?route=checkout/payment');

		return TRUE;
	}

	public function success() {

		$this->redirect(HTTP_SERVER . 'index.php?route=checkout/success');

		return TRUE;
	}

	public function callback() {

		if ($this->config->get('qiwi_mode_select')=='qiwi_mode_debug')
		{
			$this->log->write('qiwi callback');
		}

		$s = new SoapServer(DIR_CONFIG . 'ishopclientws.wsdl');
		$s->setClass('qiwiSoap');
		$s->handle();
		exit;
	}



}


class qiwiSoap extends model {


	public function __construct() {
		global $registry;
		$this->registry = $registry;
	}


	public function updateBill($param) {


		// Проверка на ID магазина
		if($param->login != $this->config->get('qiwi_shop_id')) {
			$this->log->write('qiwi error id');

			$this->log->write('qiwi send shop id '. $param->login);
			$this->log->write('qiwi my shop id '. $this->config->get('qiwi_shop_id'));

			$param->updateBillResult = 150;
			return $param;
		}


		$order_id = (int)$param->txn;
		$hash = strtoupper( md5( $order_id . strtoupper( md5($this->config->get('qiwi_password')))));
		// Проверка на пароль
		if($param->password != $hash) {
			$this->log->write('qiwi error hash');

			$this->log->write('qiwi send hash '. $param->password);
			$this->log->write('qiwi my hash '. $hash);

			$param->updateBillResult = 150;
			return $param;
		}

		// Проверка на номер заказа
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);
		if ( ! $order_info) {
			$this->log->write('qiwi error order');

			$this->log->write('qiwi send order_id '. $param->txn);

			$param->updateBillResult = 210;
			return $param;
		}


		// проверка на сумму заказа
		include(DIR_CONFIG .'ishopserverwsservice.php');
		define('TRACE', 1);
	
		$service = new IShopServerWSService(DIR_CONFIG .'ishopserverws.wsdl', array('location'      => 'http://ishop.qiwi.ru/services/ishop', 'trace' => TRACE));
	
		$params = new checkBill();
		$params->login = $this->config->get('qiwi_shop_id');
		$params->password = $this->config->get('qiwi_password');
		$params->txn = $order_id;
		
		// вызываем метод сервиса с параметрами
		$res = $service->checkBill($params);
	
		$rur_code = $this->config->get('qiwi_rub_select');
		$rur_order_total = $this->currency->convert($order_info['total'], $order_info['currency_code'], $rur_code);
		$summ = $this->currency->format($rur_order_total, $rur_code, $order_info['currency_value'], FALSE);
		$summ = round((float)$summ + (float)$summ/100*(float)$this->config->get('qiwi_markup'), 2);

	
		if ($res->amount != $summ)
		{
			$this->log->write('qiwi error amount');
	
			$param->updateBillResult = 300;
			return $param;
		}


		$param->updateBillResult = 0;

		// Изменяем статус заказа

		if ($this->config->get('qiwi_mode_select')=='qiwi_mode_debug')
		{
			$this->log->write('qiwi send status '. $param->status);
			$this->log->write('qiwi my status '. $order_info['order_status_id']);

			$this->log->write('qiwi send summ '. $res->amount);
			$this->log->write('qiwi my summ '. $summ);

			$this->log->write('qiwi admin status '. $this->config->get('qiwi_order_status_id'));
			$this->log->write('qiwi admin status cancel '. $this->config->get('qiwi_order_status_cancel_id'));
			$this->log->write('qiwi admin status progress '. $this->config->get('qiwi_order_status_progress_id'));

		}

		// Статус проведения счета.
		if( $param->status == 60 ) {

			if( $order_info['order_status_id'] == 0) {
				$this->model_checkout_order->confirm($order_id, $this->config->get('qiwi_order_status_id'), 'QIWI');
				return $param;
			}

			if( $order_info['order_status_id'] != $this->config->get('qiwi_order_status_id')) {
				$this->model_checkout_order->update($order_id, $this->config->get('qiwi_order_status_id'),'QIWI',TRUE);
			}
		} elseif( $param->status >= 150) {

			if( $order_info['order_status_id'] == 0) {
				$this->model_checkout_order->confirm($order_id, $this->config->get('qiwi_order_status_cancel_id'), 'QIWI');
				return $param;
			}

			if( $order_info['order_status_id'] != $this->config->get('qiwi_order_status_cancel_id')) {
				$this->model_checkout_order->update($order_id, $this->config->get('qiwi_order_status_cancel_id'),'QIWI',TRUE);
			}
		} else {

			if( $order_info['order_status_id'] == 0) {
				$this->model_checkout_order->confirm($order_id, $this->config->get('qiwi_order_status_progress_id'), 'QIWI');
				return $param;
			}

			if( $order_info['order_status_id'] != $this->config->get('qiwi_order_status_progress_id')) {
				$this->model_checkout_order->update($order_id, $this->config->get('qiwi_order_status_progress_id'),'QIWI',TRUE);
			}

		}


		return $param;
	}
}

?>