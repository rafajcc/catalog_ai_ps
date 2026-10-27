<?php
/**
 * Catálogo IA - Gestión segura de credenciales.
 *
 * @author    Vera Technology
 * @copyright Vera Technology
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cifra y enmascara la Clave API antes de guardarla en `Configuration`.
 *
 * El cifrado se deriva de `_COOKIE_KEY_`, por lo que el valor almacenado no
 * es legible aunque se acceda directamente a la base de datos.
 */
class CatalogIaCredentials
{
    const CIPHER = 'aes-256-cbc';
    const PREFIX = 'enc:v1:';
    const IV_LENGTH = 16;

    /**
     * @var string
     */
    private $cookieKey;

    /**
     * @param string|null $cookieKey Clave base de la instalación. Si es null se usa `_COOKIE_KEY_`.
     */
    public function __construct($cookieKey = null)
    {
        if ($cookieKey === null && defined('_COOKIE_KEY_')) {
            $cookieKey = _COOKIE_KEY_;
        }

        $this->cookieKey = (string) $cookieKey;
    }

    /**
     * Cifra un valor. Si el cifrado no está disponible devuelve el valor tal cual.
     *
     * @param string $plainValue
     *
     * @return string
     */
    public function encrypt($plainValue)
    {
        $plainValue = (string) $plainValue;

        if ($plainValue === '') {
            return '';
        }

        if (!$this->canEncrypt()) {
            return $plainValue;
        }

        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        if ($iv === false) {
            return $plainValue;
        }

        $cipherText = openssl_encrypt(
            $plainValue,
            self::CIPHER,
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipherText === false) {
            return $plainValue;
        }

        return self::PREFIX . base64_encode($iv . $cipherText);
    }

    /**
     * Descifra un valor. Los valores sin prefijo se consideran texto plano heredado.
     *
     * @param string $storedValue
     *
     * @return string
     */
    public function decrypt($storedValue)
    {
        $storedValue = (string) $storedValue;

        if ($storedValue === '') {
            return '';
        }

        if (strpos($storedValue, self::PREFIX) !== 0) {
            return $storedValue;
        }

        if (!$this->canEncrypt()) {
            return '';
        }

        $payload = base64_decode(substr($storedValue, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= self::IV_LENGTH) {
            return '';
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $cipherText = substr($payload, self::IV_LENGTH);

        $plainValue = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plainValue === false ? '' : $plainValue;
    }

    /**
     * Enmascara una clave mostrando solo los primeros y últimos caracteres.
     *
     * @param string $value
     *
     * @return string
     */
    public function mask($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = function_exists('mb_substr') ? mb_substr($value, 0, 4, 'UTF-8') : substr($value, 0, 4);
        $suffix = function_exists('mb_substr')
            ? mb_substr($value, -4, 4, 'UTF-8')
            : substr($value, -4, 4);

        return $prefix . str_repeat('*', $length - 8) . $suffix;
    }

    /**
     * Indica si se puede usar cifrado simétrico.
     *
     * @return bool
     */
    private function canEncrypt()
    {
        return $this->cookieKey !== ''
            && function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt');
    }

    /**
     * Deriva una clave de 32 bytes a partir de `_COOKIE_KEY_`.
     *
     * @return string
     */
    private function getEncryptionKey()
    {
        return hash('sha256', $this->cookieKey . '|catalog_ia_ps|credentials', true);
    }
}
