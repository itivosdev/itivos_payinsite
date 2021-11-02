<?php
class Itivos_PayinsiteValidationModuleFrontController extends ModuleFrontController
{
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirect('index.php?controller=order&step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'itivos_payinsite')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.ItivosPayinsite.Shop'));

		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirect('index.php?controller=order&step=1');

		$currency = $this->context->currency;
		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$mailVars = array(
			'{itivosPayInSiteDetails}' => Configuration::get('ITIVOSPAYINSITE_DETAILS'),
			'{itivosPayInSiteCustomText}' => nl2br(Configuration::get('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT'))
		);

		$this->module->validateOrder($cart->id, Configuration::get('WAITING_PAYMENT_IN_STORE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
	}
}
