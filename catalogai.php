<?php
/**
 * Copyright (c) 2026 Vera Technology; rafajcc
 * Licensed under Business Source License 1.1 (BSL 1.1)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'catalogai/classes/CatalogaiApiClient.php';

class Catalogai extends Module
{
    public function __construct()
    {
        $this->name = 'catalogai';
        $this->tab = 'administration';
        $this->version = '1.0.24';
        $this->author = 'Vera Technology';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => '9.99.99');

        parent::__construct();

        $this->displayName = $this->l('Catalog AI');
        $this->description = $this->l('Conector ligero con el servicio SaaS Catalog AI para generar descripciones, meta-datos e imágenes sugeridas y aplicarlas a tus productos.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que deseas desinstalar este módulo? Se eliminarán los ajustes configurados.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('actionAdminControllerSetMedia')
            || !$this->registerHook('displayAdminProductsExtra')
        ) {
            return false;
        }

        Configuration::updateValue('CATALOGAI_TIMEOUT', 30);

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        Configuration::deleteByName('CATALOGAI_API_URL');
        Configuration::deleteByName('CATALOGAI_API_KEY');
        Configuration::deleteByName('CATALOGAI_TIMEOUT');

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitCatalogaiConfig') && !Tools::isSubmit('submitCatalogaiTest')) {
            $apiUrl = Tools::getValue('CATALOGAI_API_URL');
            $apiKey = Tools::getValue('CATALOGAI_API_KEY');
            $timeout = (int) Tools::getValue('CATALOGAI_TIMEOUT');

            if (empty($apiUrl)) {
                $output .= $this->displayError($this->l('Debes introducir la URL de la API.'));
            } elseif ($apiKey === false || trim($apiKey) === '' || $apiKey === '********') {
                $savedKey = Configuration::get('CATALOGAI_API_KEY');
                if (empty($savedKey)) {
                    $output .= $this->displayError($this->l('Debes introducir la clave API.'));
                } else {
                    $apiUrlTrimmed = trim(rtrim($apiUrl, '/'));
                    if (strpos($apiUrlTrimmed, 'http://') !== 0 && strpos($apiUrlTrimmed, 'https://') !== 0) {
                        $output .= $this->displayError($this->l('La URL debe empezar por http:// o https://'));
                    } else {
                        Configuration::updateValue('CATALOGAI_API_URL', $apiUrlTrimmed);
                        if ($timeout >= 5 && $timeout <= 120) {
                            Configuration::updateValue('CATALOGAI_TIMEOUT', $timeout);
                        } else {
                            Configuration::updateValue('CATALOGAI_TIMEOUT', 30);
                        }
                        $output .= $this->displayConfirmation($this->l('Configuración guardada correctamente.'));
                    }
                }
            } else {
                $apiUrlTrimmed = trim(rtrim($apiUrl, '/'));

                if (strpos($apiUrlTrimmed, 'http://') !== 0 && strpos($apiUrlTrimmed, 'https://') !== 0) {
                    $output .= $this->displayError($this->l('La URL debe empezar por http:// o https://'));
                } else {
                    Configuration::updateValue('CATALOGAI_API_URL', $apiUrlTrimmed);

                    if ($apiKey !== false && $apiKey !== '' && $apiKey !== '********') {
                        $apiKeyTrimmed = trim($apiKey);
                        if ($apiKeyTrimmed !== '') {
                            Configuration::updateValue('CATALOGAI_API_KEY', $apiKeyTrimmed);
                        }
                    }

                    if ($timeout >= 5 && $timeout <= 120) {
                        Configuration::updateValue('CATALOGAI_TIMEOUT', $timeout);
                    } else {
                        Configuration::updateValue('CATALOGAI_TIMEOUT', 30);
                    }

                    $output .= $this->displayConfirmation($this->l('Configuración guardada correctamente.'));
                }
            }
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $savedKey = Configuration::get('CATALOGAI_API_KEY');
        $keyPlaceholder = !empty($savedKey) ? '********' : '';
        $keyDesc = $this->l('Clave API generada en el panel de Catalog AI. Déjalo vacío para mantener la clave guardada.');
        if (!empty($savedKey)) {
            $len = strlen($savedKey);
            if ($len > 12) {
                $masked = substr($savedKey, 0, 6) . str_repeat('*', 14) . substr($savedKey, -4);
            } else {
                $masked = str_repeat('*', $len);
            }
            $keyDesc .= '<br /><strong style="color:#00b9d8;">' . $this->l('Clave API guardada:') . ' ' . $masked . '</strong>';
        }

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuración de Catalog AI'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('URL del servicio Catalog AI'),
                        'name' => 'CATALOGAI_API_URL',
                        'required' => true,
                        'placeholder' => 'https://tu-servidor-catalog-ai',
                        'desc' => $this->l('URL del backend de Catalog AI con https://')
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Clave API (Bearer)'),
                        'name' => 'CATALOGAI_API_KEY',
                        'required' => true,
                        'placeholder' => $keyPlaceholder,
                        'desc' => $keyDesc
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Tiempo de espera (segundos)'),
                        'name' => 'CATALOGAI_TIMEOUT',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Timeout para las llamadas a la API. Por defecto 30 segundos.')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                    'name' => 'submitCatalogaiConfig'
                ),
                'buttons' => array(
                    array(
                        'type' => 'button',
                        'title' => $this->l('Probar conexión'),
                        'icon' => 'process-icon-refresh',
                        'id' => 'catalogai-test-connection'
                    )
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCatalogaiConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'CATALOGAI_API_URL' => Tools::getValue('CATALOGAI_API_URL', Configuration::get('CATALOGAI_API_URL')),
            'CATALOGAI_API_KEY' => '',
            'CATALOGAI_TIMEOUT' => Tools::getValue('CATALOGAI_TIMEOUT', Configuration::get('CATALOGAI_TIMEOUT') ? Configuration::get('CATALOGAI_TIMEOUT') : 30),
        );
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        if (!isset($this->context->controller->controller_name)) {
            return;
        }

        $controller = $this->context->controller->controller_name;

        if ($controller == 'AdminProducts') {
            $this->context->controller->addJS($this->_path . 'views/js/catalogai.js');
            $this->context->controller->addCSS($this->_path . 'views/css/catalogai.css');
        }

        if ($controller == 'AdminModules' && Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/catalogai-config.js');
            Media::addJsDef(array(
                'catalogaiTestUrl' => $this->context->link->getAdminLink('AdminModules', true, array(), array(
                    'configure' => $this->name,
                    'ajax' => 1,
                    'action' => 'testConnection'
                ))
            ));
        }
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        if (!isset($params['id_product'])) {
            return '';
        }

        $apiUrl = Configuration::get('CATALOGAI_API_URL');
        $apiKey = Configuration::get('CATALOGAI_API_KEY');
        if (empty($apiUrl) || empty($apiKey)) {
            return '<div class="alert alert-info"><strong>' . $this->l('Catalog AI') . ':</strong> ' . $this->l('Configura la URL y la clave API en la configuración del módulo para usar esta funcionalidad.') . '</div>';
        }

        $product = new Product((int) $params['id_product'], false, $this->context->language->id);
        $this->context->smarty->assign(array(
            'catalogai_product' => $product,
            'catalogai_ajax_url' => $this->context->link->getAdminLink('AdminCatalogaiAjax'),
            'catalogai_token' => Tools::getAdminTokenLite('AdminCatalogaiAjax'),
        ));

        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }

    public function ajaxProcessTestConnection()
    {
        $apiUrl = Tools::getValue('CATALOGAI_API_URL');
        if (empty($apiUrl)) {
            $apiUrl = Configuration::get('CATALOGAI_API_URL');
        }
        $apiUrl = trim(rtrim($apiUrl, '/'));

        $apiKey = Tools::getValue('CATALOGAI_API_KEY');
        if ($apiKey === false || $apiKey === '' || $apiKey === '********') {
            $apiKey = Configuration::get('CATALOGAI_API_KEY');
        } else {
            $apiKey = trim($apiKey);
        }

        $timeout = (int) Tools::getValue('CATALOGAI_TIMEOUT');
        if ($timeout <= 0) {
            $timeout = (int) Configuration::get('CATALOGAI_TIMEOUT');
        }
        if ($timeout <= 0) {
            $timeout = 30;
        }

        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog('[CatalogAI][TEST] Intento | URL: ' . $apiUrl . ' | Endpoint: /api/public/status | Timeout: ' . $timeout . 's | Clave: ' . (!empty($apiKey) ? 'SI' : 'NO'), 1, null, 'Catalogai', null, true);
        }

        if (empty($apiUrl)) {
            $msg = $this->l('Por favor configura la URL de la API primero.');
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[CatalogAI][TEST] ERROR: ' . $msg, 3, null, 'Catalogai', null, true);
            }
            die(json_encode(array('success' => false, 'message' => $msg)));
        }

        if (empty($apiKey)) {
            $msg = $this->l('Debes introducir la clave API.');
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[CatalogAI][TEST] ERROR: ' . $msg, 3, null, 'Catalogai', null, true);
            }
            die(json_encode(array('success' => false, 'message' => $msg)));
        }

        if (strpos($apiUrl, 'http://') !== 0 && strpos($apiUrl, 'https://') !== 0) {
            $msg = $this->l('La URL debe empezar por http:// o https://');
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[CatalogAI][TEST] ERROR: ' . $msg, 3, null, 'Catalogai', null, true);
            }
            die(json_encode(array('success' => false, 'message' => $msg)));
        }

        try {
            require_once _PS_MODULE_DIR_ . 'catalogai/classes/CatalogaiApiClient.php';
            @file_put_contents(_PS_MODULE_DIR_ . 'catalogai/debug_test.log', '[' . date('Y-m-d H:i:s') . '] AJAX ENTER | URL=' . $apiUrl . ' | KEY=' . (!empty($apiKey) ? 'YES' : 'NO') . PHP_EOL, FILE_APPEND);
            $client = new CatalogaiApiClient($apiUrl, $apiKey, $timeout);
            $endpoint = '/api/public/status';
            @file_put_contents(_PS_MODULE_DIR_ . 'catalogai/debug_test.log', '[' . date('Y-m-d H:i:s') . '] CALLING ENDPOINT=' . $endpoint . PHP_EOL, FILE_APPEND);
            $response = $client->get($endpoint);
            @file_put_contents(_PS_MODULE_DIR_ . 'catalogai/debug_test.log', '[' . date('Y-m-d H:i:s') . '] RESPONSE=' . json_encode($response) . PHP_EOL, FILE_APPEND);

            $isOk = false;
            if (is_array($response)) {
                if (isset($response['data']['online']) && $response['data']['online'] === true) {
                    $isOk = true;
                } elseif (!empty($response['success'])) {
                    $isOk = true;
                }
            }

            if ($isOk) {
                if (class_exists('PrestaShopLogger')) {
                    PrestaShopLogger::addLog('[CatalogAI][TEST] ÉXITO | ' . $apiUrl . '/api/public/status | Respuesta: ' . json_encode($response), 1, null, 'Catalogai', null, true);
                }
                die(json_encode(array('success' => true, 'message' => $this->l('Conexión correcta con el servicio Catalog AI.'))));
            }

            $msg = $this->l('Respuesta inesperada del servicio.');
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[CatalogAI][TEST] FRACASO | ' . $apiUrl . '/api/public/status | Respuesta: ' . json_encode($response), 3, null, 'Catalogai', null, true);
            }
            die(json_encode(array('success' => false, 'message' => $msg)));
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[CatalogAI][TEST] EXCEPCIÓN | ' . $apiUrl . '/api/public/status | ' . $msg, 3, null, 'Catalogai', null, true);
            }
            die(json_encode(array('success' => false, 'message' => $msg)));
        }
    }

    public static function getModuleTranslation($module, $string, $source = null)
    {
        if (method_exists('Translate', 'getModuleTranslation')) {
            return Translate::getModuleTranslation($module, $string, $source ?: $module);
        }
        return $string;
    }
}
