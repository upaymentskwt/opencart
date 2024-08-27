<?php
class ModelExtensionPaymentUpay extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/upay');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_upay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get('payment_upay_total') > 0 && $this->config->get('payment_upay_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_upay_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'upay',
				'terms'      => '',
				'title'      => '<img src="https://upay.upayments.com/assets/global/img/opencart_img.png" style="height:50px;" />',
				'sort_order' => $this->config->get('payment_tap_sort_order')
			);
		}

		return $method_data;
	}
}
