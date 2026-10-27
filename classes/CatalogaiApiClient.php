<?php
/**
 * Copyright (c) 2026 Vera Technology; rafajcc
 * Licensed under Business Source License 1.1 (BSL 1.1)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CatalogaiApiClient
{
    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @param string $apiUrl
     * @param string $apiKey
     * @param int $timeout
     */
    public function __construct($apiUrl, $apiKey, $timeout = 30)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = (int) $timeout;
        if ($this->timeout <= 0) {
            $this->timeout = 30;
        }
    }

    /**
     * @param string $endpoint
     * @param array $payload
     * @return array
     * @throws Exception
     */
    public function post($endpoint, array $payload = array())
    {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init($url);
        
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: PrestaShop-CatalogAI-Module'
        );
        
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        if ($curlErrno !== 0) {
            throw new Exception($this->l('Error de conexión: ') . $curlError);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = $this->l('Error HTTP ') . $httpCode;
            if (!empty($response)) {
                $decoded = json_decode($response, true);
                if (isset($decoded['error']['message'])) {
                    $errorMsg = $decoded['error']['message'];
                } elseif (isset($decoded['message'])) {
                    $errorMsg = $decoded['message'];
                }
            }
            throw new Exception($errorMsg);
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception($this->l('Respuesta inválida de la API'));
        }
        
        return $decoded;
    }

    /**
     * @param string $endpoint
     * @return array
     * @throws Exception
     */
    public function get($endpoint)
    {
        $url = $this->apiUrl . $endpoint;
        @file_put_contents(_PS_MODULE_DIR_ . 'catalogai/debug_client.log', '[' . date('Y-m-d H:i:s') . '] GET ENDPOINT=' . $endpoint . ' FULL=' . $url . PHP_EOL, FILE_APPEND);

        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog('[CatalogAI][CLIENT] GET -> ENDPOINT:[' . $endpoint . '] FULL:' . $url, 1, null, 'Catalogai', null, true);
        }

        $ch = curl_init($url);

        $headers = array(
            'Accept: application/json',
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Pragma: no-cache',
            'User-Agent: PrestaShop-CatalogAI-Module'
        );

        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog('[CatalogAI][CLIENT] GET <- ' . $url . ' | HTTP: ' . $httpCode . ' | cURL: ' . ($curlErrno === 0 ? 'OK' : 'ERR' . $curlErrno), ($curlErrno !== 0 || $httpCode < 200 || $httpCode >= 300) ? 3 : 1, null, 'Catalogai', null, true);
        }

        if ($curlErrno !== 0) {
            throw new Exception($this->l('Error de conexión: ') . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = $this->l('Error HTTP ') . $httpCode;
            if (!empty($response)) {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    if (isset($decoded['error']['message'])) {
                        $errorMsg = $decoded['error']['message'];
                    } elseif (isset($decoded['message'])) {
                        $errorMsg = $decoded['message'];
                    }
                }
            }
            throw new Exception($errorMsg);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception($this->l('Respuesta inválida de la API'));
        }

        return $decoded;
    }

    /**
     * @param string $key
     * @return string
     */
    private function l($key)
    {
        if (class_exists('Catalogai')) {
            return Catalogai::getModuleTranslation('catalogai', $key, 'CatalogaiApiClient');
        }
        return $key;
    }
}
