<?php
/**
 * Copyright (c) 2026 Vera Technology; rafajcc
 * Licensed under Business Source License 1.1 (BSL 1.1)
 */

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'catalogai/classes/CatalogaiApiClient.php';

$context = Context::getContext();
$module = Module::getInstanceByName('catalogai');

if (!$module) {
    die(json_encode(array('success' => false, 'error' => 'Module not found')));
}

$token = Tools::getValue('token');
if ($token != Tools::getAdminTokenLite('AdminCatalogaiAjax')) {
    die(json_encode(array('success' => false, 'error' => 'Invalid token')));
}

$action = Tools::getValue('action');

header('Content-Type: application/json');

if ($action == 'generate') {
    $apiUrl = Configuration::get('CATALOGAI_API_URL');
    $apiKey = Configuration::get('CATALOGAI_API_KEY');
    $timeout = (int) Configuration::get('CATALOGAI_TIMEOUT');
    if ($timeout <= 0) {
        $timeout = 30;
    }

    if (empty($apiUrl)) {
        die(json_encode(array('success' => false, 'error' => 'URL de API no configurada')));
    }
    if (empty($apiKey)) {
        die(json_encode(array('success' => false, 'error' => 'Clave API no configurada')));
    }

    $productJson = Tools::getValue('product');
    $productData = array();
    if (!empty($productJson)) {
        $productData = json_decode($productJson, true);
    }
    if (empty($productData) || !is_array($productData)) {
        $productId = (int) Tools::getValue('product_id');
        $product = new Product($productId, false, $context->language->id);
        $productData = array(
            'id' => (string) $productId,
            'reference' => $product->reference,
            'name' => $product->name,
            'ean' => $product->ean13,
            'description' => $product->description,
            'description_short' => $product->description_short,
        );
        $manufacturer = new Manufacturer((int) $product->id_manufacturer, $context->language->id);
        if (Validate::isLoadedObject($manufacturer)) {
            $productData['brand'] = $manufacturer->name;
        }
    }

    try {
        $client = new CatalogaiApiClient($apiUrl, $apiKey, $timeout);
        $response = $client->post('/api/public/autocomplete', array(
            'product' => $productData,
            'language' => $context->language->iso_code
        ));
        die(json_encode($response));
    } catch (Exception $e) {
        die(json_encode(array('success' => false, 'error' => $e->getMessage())));
    }
}

die(json_encode(array('success' => false, 'error' => 'Acción no válida')));
