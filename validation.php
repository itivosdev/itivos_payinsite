<?php

include(__DIR__.'/../../config/config.inc.php');
include(__DIR__.'/../../header.php');
include(__DIR__.'/../../init.php');

$context = Context::getContext();
$cart = $context->cart;
$itivospayinsite = Module::getInstanceByName('itivos_payinsite');

if ($cart->id_customer == 0 OR $cart->id_address_delivery == 0 OR $cart->id_address_invoice == 0 OR !$itivospayinsite->active)
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
	die($itivospayinsite->getTranslator()->trans('This payment method is not available.', array(), 'Modules.ItivosPayinsite.Shop'));

$customer = new Customer((int)$cart->id_customer);

if (!Validate::isLoadedObject($customer))
	Tools::redirect('index.php?controller=order&step=1');

$currency = $context->currency;
$total = (float)($cart->getOrderTotal(true, Cart::BOTH));

$itivospayinsite->validateOrder($cart->id, Configuration::get('WAITING_PAYMENT_IN_STORE'), $total, $itivospayinsite->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);

$order = new Order($itivospayinsite->currentOrder);
Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$itivospayinsite->id.'&id_order='.$itivospayinsite->currentOrder.'&key='.$customer->secure_key);
