<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Itivos_Payinsite extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'ITIVOS_PAYMENT_INVITE';
    const WAITING_PAYMENT_IN_STORE = 'WAITING_PAYMENT_IN_STORE';

    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'itivos_payinsite';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        $this->author = 'Bernardo Fuentes';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('ITIVOSPAYINSITE_DETAILS', 'ITIVOS_WIRE_RESERVATION_DAYS'));

        if (!empty($config['ITIVOSPAYINSITE_DETAILS'])) {
            $this->details = $config['ITIVOSPAYINSITE_DETAILS'];
        }
        if (!empty($config['ITIVOS_WIRE_RESERVATION_DAYS'])) {
            $this->reservation_days = $config['ITIVOS_WIRE_RESERVATION_DAYS'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Payment in Site');
        $this->description = $this->l('Accept payments in the store and show during the checkout');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');
        if (!isset($this->details)) {
            $this->warning = $this->l('You must configure this module before using it.');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->trans('No currency has been set for this module.', array(), 'Modules.ItivosPayinsite.Admin');
        }

        $this->extra_mail_vars = array(
                                        '{details_itivospay}' => Configuration::get('DETAILS_ITIVOSPAYINSITE'),
                                        );
    }

    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }
        if (!$this->installOrderState()) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            if (!Configuration::deleteByName('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT', $lang['id_lang'])) {
                return false;
            }
        }

        if (!Configuration::deleteByName('ITIVOSPAYINSITE_DETAILS')
                || !Configuration::deleteByName('ITIVOS_WIRE_RESERVATION_DAYS')
                || !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE));

            if (!Tools::getValue('ITIVOSPAYINSITE_DETAILS')) {
                $this->_postErrors[] = $this->trans('Account details are required.', array(), 'Modules.ItivosPayinsite.Admin');
            } 
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ITIVOSPAYINSITE_DETAILS', Tools::getValue('ITIVOSPAYINSITE_DETAILS'));

            $custom_text = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                if (Tools::getIsset('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT_'.$lang['id_lang'])) {
                    $custom_text[$lang['id_lang']] = Tools::getValue('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT_'.$lang['id_lang']);
                }
            }
            Configuration::updateValue('ITIVOS_WIRE_RESERVATION_DAYS', Tools::getValue('ITIVOS_WIRE_RESERVATION_DAYS'));
            Configuration::updateValue('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT', $custom_text);
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    public function installOrderState()
    {
        if (!Configuration::get(self::WAITING_PAYMENT_IN_STORE)
            || !Validate::isLoadedObject(new OrderState(Configuration::get(self::WAITING_PAYMENT_IN_STORE)))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                switch (Tools::strtolower($language['iso_code'])) {
                    case 'fr':
                        $order_state->name[$language['id_lang']] = 'Waiting for payment in store';
                        break;
                    case 'es':
                        $order_state->name[$language['id_lang']] = 'Esperando pago en tienda';
                        break;
                    case 'mx':
                        $order_state->name[$language['id_lang']] = 'Esperando pago en tienda';
                        break;
                    default:
                        $order_state->name[$language['id_lang']] = 'En attente de paiement en magasin';
                        break;
                }
            }
            $order_state->send_email = true;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_.'itivos_payinsite/views/img/cc-sofort.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }
            Configuration::updateValue(self::WAITING_PAYMENT_IN_STORE, (int) $order_state->id);
        }

        return true;
    }

    protected function _displayPayInSite()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->_displayPayInSite();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay in store'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:itivos_payinsite/views/templates/hook/itivos_payinsite_intro.tpl'));
        $payment_options = [
            $newOption,
        ];
       
        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active || !Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (
            in_array(
                $state,
                array(
                    Configuration::get('WAITING_PAYMENT_IN_STORE'),
                    Configuration::get('PS_OS_OUTOFSTOCK'),
                    Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
                )
        )) {

            $itivosPayInSiteDetails = Tools::nl2br($this->details);
            if (!$itivosPayInSiteDetails) {
                $itivosPayInSiteDetails = '___________';
            }

            $totalToPaid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();
            $this->smarty->assign(array(
                'shop_name' => $this->context->shop->name,
                'total' => Tools::displayPrice(
                    $totalToPaid,
                    new Currency($params['order']->id_currency),
                    false
                ),
                'itivosPayInSiteDetails' => $itivosPayInSiteDetails,
                'status' => 'ok',
                'reference' => $params['order']->reference,
                'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                    'status' => 'failed',
                    'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:itivos_payinsite/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Account details'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Details for pay'),
                        'name' => 'ITIVOSPAYINSITE_DETAILS',
                        'desc' => $this->l('Please enter all the details to make the payment'),
                        'required' => false
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $fields_form_customization = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Customization'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Reservation period'),
                        'desc' => $this->l('Number of days the items remain reserved'),
                        'name' => 'ITIVOS_WIRE_RESERVATION_DAYS',
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Information to the customer'),
                        'name' => 'ITIVOS_PAY_IN_SITE_CUSTOM_TEXT',
                        'desc' => $this->l('Information on the bank transfer (processing time, starting of the shipping...'),
                        'lang' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Display the invitation to pay in the order confirmation page'),
                        'name' => self::FLAG_DISPLAY_PAYMENT_INVITE,
                        'is_bool' => true,
                        'hint' => $this->l('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form, $fields_form_customization));
    }

    public function getConfigFieldsValues()
    {
        $custom_text = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $custom_text[$lang['id_lang']] = Tools::getValue(
                'ITIVOS_PAY_IN_SITE_CUSTOM_TEXT_'.$lang['id_lang'],
                Configuration::get('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT', $lang['id_lang'])
            );
        }

        return array(
            'ITIVOSPAYINSITE_DETAILS' => Tools::getValue('ITIVOSPAYINSITE_DETAILS', Configuration::get('ITIVOSPAYINSITE_DETAILS')),
            'ITIVOS_WIRE_RESERVATION_DAYS' => Tools::getValue('ITIVOS_WIRE_RESERVATION_DAYS', Configuration::get('ITIVOS_WIRE_RESERVATION_DAYS')),
            'ITIVOS_PAY_IN_SITE_CUSTOM_TEXT' => $custom_text,
            self::FLAG_DISPLAY_PAYMENT_INVITE => Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE,
                Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE))
        );
    }

    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $total = sprintf(
            $this->trans('%1$s (tax incl.)', array(), 'Modules.ItivosPayinsite.Shop'),
            Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
        );


        $itivosPayInSiteDetails = Tools::nl2br($this->details);
        if (!$itivosPayInSiteDetails) {
            $itivosPayInSiteDetails = '___________';
        }

        $itivosPayInSiteReservation_days = Configuration::get('ITIVOS_WIRE_RESERVATION_DAYS');
        if (false === $itivosPayInSiteReservation_days) {
            $itivosPayInSiteReservation_days = 7;
        }

        $itivosPayInSiteCustomText = Tools::nl2br(Configuration::get('ITIVOS_PAY_IN_SITE_CUSTOM_TEXT', $this->context->language->id));
        if (false === $itivosPayInSiteCustomText) {
            $itivosPayInSiteCustomText = '';
        }

        return array(
            'total' => $total,
            'itivosPayInSiteDetails' => $itivosPayInSiteDetails,
            'itivosPayInSiteReservation_days' => (int)$itivosPayInSiteReservation_days,
            'itivosPayInSiteCustomText' => $itivosPayInSiteCustomText,
        );
    }
}
