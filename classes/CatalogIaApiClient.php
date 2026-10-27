<?php
/**
 * Catálogo IA - Cliente HTTP del servicio.
 *
 * @author    Catálogo IA
 * @copyright Catálogo IA
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cliente para el endpoint público de estado de Catálogo IA.
 */
class CatalogIaApiClient
{
    const STATUS_PATH = '/api/public/status';
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_CONNECT_TIMEOUT = 10;
    const MAX_RESPONSE_BYTES = 1048576;
    const USER_AGENT = 'CatalogIA-PrestaShop/0.0.1';

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $connectTimeout;

    /**
     * @param int $timeout        Tiempo máximo total de la petición, en segundos.
     * @param int $connectTimeout Tiempo máximo de conexión, en segundos.
     */
    public function __construct($timeout = self::DEFAULT_TIMEOUT, $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT)
    {
        $this->timeout = (int) $timeout > 0 ? (int) $timeout : self::DEFAULT_TIMEOUT;
        $this->connectTimeout = (int) $connectTimeout > 0 ? (int) $connectTimeout : self::DEFAULT_CONNECT_TIMEOUT;

        if ($this->connectTimeout > $this->timeout) {
            $this->connectTimeout = $this->timeout;
        }
    }

    /**
     * Comprueba que una URL es válida y usa HTTPS.
     *
     * @param string $url
     *
     * @return bool
     */
    public static function isSecureUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme)
            && strtolower($scheme) === 'https'
            && is_string($host)
            && $host !== '';
    }

    /**
     * Consulta el estado del servicio.
     *
     * @param string $baseUrl URL base del servicio.
     * @param string $apiKey  Clave API en claro.
     *
     * @return array{success: bool, code: string, message: string, http_code: int, data?: array}
     */
    public function getStatus($baseUrl, $apiKey)
    {
        $endpoint = $this->buildEndpoint($baseUrl);
        if ($endpoint === null) {
            return $this->failure('invalid_url');
        }

        $apiKey = (string) $apiKey;
        if ($apiKey === '') {
            return $this->failure('invalid_key');
        }

        if (!function_exists('curl_init')) {
            return $this->failure('curl_missing');
        }

        $handle = curl_init();
        if ($handle === false) {
            return $this->failure('curl_init');
        }

        $options = array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPGET => true,
        );

        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $errorMessage = curl_error($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== 0 || $response === false) {
            if ($errorNumber === CURLE_OPERATION_TIMEDOUT) {
                return $this->failure('timeout', array('http_code' => $httpCode, 'detail' => $errorMessage));
            }

            return $this->failure('network', array('http_code' => $httpCode, 'detail' => $errorMessage));
        }

        if (!is_string($response) || strlen($response) > self::MAX_RESPONSE_BYTES) {
            return $this->failure('too_large', array('http_code' => $httpCode));
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            if ($httpCode >= 400) {
                return $this->failure('http', array('http_code' => $httpCode));
            }

            return $this->failure('invalid_response', array('http_code' => $httpCode));
        }

        if (isset($decoded['success']) && $decoded['success'] === true) {
            return array(
                'success' => true,
                'code' => '',
                'message' => '',
                'http_code' => $httpCode,
                'data' => isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : array(),
            );
        }

        return $this->extractApiError($decoded, $httpCode);
    }

    /**
     * Construye el endpoint de estado a partir de la URL base.
     *
     * @param string $baseUrl
     *
     * @return string|null
     */
    private function buildEndpoint($baseUrl)
    {
        if (!self::isSecureUrl($baseUrl)) {
            return null;
        }

        return rtrim($baseUrl, '/') . self::STATUS_PATH;
    }

    /**
     * Extrae el error devuelto por la API.
     *
     * @param array $decoded
     * @param int   $httpCode
     *
     * @return array
     */
    private function extractApiError(array $decoded, $httpCode)
    {
        $message = '';
        $apiCode = '';
        $statusCode = $httpCode;

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            }

            if (isset($decoded['error']['code']) && is_string($decoded['error']['code'])) {
                $apiCode = $decoded['error']['code'];
            }

            if (isset($decoded['error']['statusCode'])) {
                $statusCode = (int) $decoded['error']['statusCode'];
            }
        }

        if ($message === '' && $apiCode === '') {
            if ($statusCode < 200 || $statusCode >= 300) {
                return $this->failure('http', array('http_code' => $statusCode));
            }

            return $this->failure('invalid_response', array('http_code' => $statusCode));
        }

        return array(
            'success' => false,
            'code' => 'api',
            'message' => $message,
            'api_code' => $apiCode,
            'http_code' => $statusCode,
        );
    }

    /**
     * Devuelve un resultado de error normalizado.
     *
     * @param string $code
     * @param array  $extra
     *
     * @return array
     */
    private function failure($code, array $extra = array())
    {
        return array_merge(
            array(
                'success' => false,
                'code' => $code,
                'message' => '',
                'http_code' => 0,
            ),
            $extra
        );
    }
}
