<?php
/**
 * Catálogo IA - Módulo de PrestaShop.
 *
 * @author    Vera Technology
 * @copyright Vera Technology
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/CatalogIaApiClient.php';
require_once __DIR__ . '/classes/CatalogIaCredentials.php';

class Catalog_Ia_Ps extends Module
{
    const CONFIG_URL = 'CATALOG_IA_PS_URL';
    const CONFIG_API_KEY = 'CATALOG_IA_PS_API_KEY';
    // Campo oculto que transporta (cifrada) la clave tecleada en el intento
    // anterior, para no perderla al repintar el formulario tras probar la
    // conexión. Nunca contiene la clave en claro.
    const CONFIG_API_KEY_DRAFT = 'CATALOG_IA_PS_API_KEY_DRAFT';
    const DRAFT_CIPHER_PREFIX = 'enc:v1:';

    const TRANSLATION_DOMAIN = 'Modules.Catalogiaps.Admin';

    /**
     * @var string|null URL a mostrar en el formulario (la enviada, si hubo POST).
     */
    private $urlForDisplay = null;

    /**
     * @var string|null Clave real (sin enmascarar) a mostrar en el formulario.
     */
    private $apiKeyForDisplay = null;

    public function __construct()
    {
        $this->name = 'catalog_ia_ps';
        $this->tab = 'administration';
        $this->version = '0.0.1';
        $this->author = 'Vera Technology';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => '9.99.99');

        parent::__construct();

        $this->displayName = $this->trans('Catálogo IA', array(), self::TRANSLATION_DOMAIN);
        $this->description = $this->trans(
            'Módulo de integración con el servicio Catálogo IA.',
            array(),
            self::TRANSLATION_DOMAIN
        );

        $this->confirmUninstall = $this->trans(
            '¿Está seguro de que desea desinstalar el módulo Catálogo IA?',
            array(),
            self::TRANSLATION_DOMAIN
        );
    }

    /**
     * {@inheritdoc}
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('actionAdminControllerSetMedia')
        ) {
            return false;
        }

        if (Configuration::get(self::CONFIG_URL) === false) {
            Configuration::updateValue(self::CONFIG_URL, '');
        }

        if (Configuration::get(self::CONFIG_API_KEY) === false) {
            Configuration::updateValue(self::CONFIG_API_KEY, '');
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        Configuration::deleteByName(self::CONFIG_URL);
        Configuration::deleteByName(self::CONFIG_API_KEY);

        return true;
    }

    /**
     * Carga el script de validación en el navegador solo en la configuración del módulo.
     *
     * @return void
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') !== $this->name) {
            return;
        }

        $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/configuration.css');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/configuration.js');

        Media::addJsDef(array(
            'catalogIaPsMessages' => array(
                'urlRequired' => $this->trans(
                    'La URL del servicio es obligatoria.',
                    array(),
                    self::TRANSLATION_DOMAIN
                ),
                'urlInvalid' => $this->trans(
                    'Introduzca una URL válida que utilice HTTPS.',
                    array(),
                    self::TRANSLATION_DOMAIN
                ),
                'keyRequired' => $this->trans(
                    'La Clave API es obligatoria.',
                    array(),
                    self::TRANSLATION_DOMAIN
                ),
            ),
        ));
    }

    /**
     * Renderiza la pantalla de configuración del módulo.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (!$this->isHttpsRequest()) {
            $output .= $this->displayWarning(
                $this->trans(
                    'Está accediendo al back office por HTTP. La Clave API viajará sin cifrar al guardar o probar la conexión. Active HTTPS en PrestaShop y acceda mediante https:// para proteger las credenciales.',
                    array(),
                    self::TRANSLATION_DOMAIN
                )
            );
        }

        $isSaveAction = Tools::isSubmit('submitCatalogIaPs');
        $isTestAction = Tools::isSubmit('submitCatalogIaPsTest');

        if ($isSaveAction || $isTestAction) {
            // Conserva lo que el usuario ha escrito aunque haya un error.
            $this->urlForDisplay = $this->getPostedValue(self::CONFIG_URL);
        }

        if (($isSaveAction || $isTestAction) && !$this->isTokenValid()) {
            return $this->displayError(
                $this->trans(
                    'Token de seguridad no válido. Recargue la página e inténtelo de nuevo.',
                    array(),
                    self::TRANSLATION_DOMAIN
                )
            ) . $this->renderForm();
        }

        if ($isTestAction) {
            $output .= $this->processTestConnection();
        } elseif ($isSaveAction) {
            $output .= $this->processSave();
        }

        return $output . $this->renderForm();
    }

    /**
     * Valida el token CSRF del formulario.
     *
     * @return bool
     */
    private function isTokenValid()
    {
        $token = Tools::getValue('token');

        return is_string($token) && $token === Tools::getAdminTokenLite('AdminModules');
    }

    /**
     * Indica si la petición actual se ha realizado por HTTPS.
     *
     * Cubre también instalaciones detrás de proxy inverso que terminan el TLS.
     *
     * @return bool
     */
    private function isHttpsRequest()
    {
        if (method_exists('Tools', 'usingSecureMode') && Tools::usingSecureMode()) {
            return true;
        }

        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwardedProto = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
            if (strtolower(trim($forwardedProto[0])) === 'https') {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda la configuración enviada desde el formulario.
     *
     * @return string
     */
    private function processSave()
    {
        $url = $this->getPostedValue(self::CONFIG_URL);
        $submittedApiKey = $this->getPostedValue(self::CONFIG_API_KEY);

        $credentials = new CatalogIaCredentials();
        $storedApiKey = $credentials->decrypt((string) Configuration::get(self::CONFIG_API_KEY));
        $apiKeyToSave = $this->resolveApiKey($submittedApiKey, $storedApiKey, $credentials);

        // Se repinta lo enviado para que el usuario pueda corregir el error
        // sin volver a teclear la clave.
        $this->apiKeyForDisplay = $apiKeyToSave;

        $errors = $this->validateCredentials($url, $apiKeyToSave);
        if (!empty($errors)) {
            return $this->renderErrors($errors);
        }

        Configuration::updateValue(self::CONFIG_URL, $url);
        Configuration::updateValue(self::CONFIG_API_KEY, $credentials->encrypt($apiKeyToSave));

        return $this->displayConfirmation(
            $this->trans('Configuración guardada correctamente.', array(), self::TRANSLATION_DOMAIN)
        );
    }

    /**
     * Prueba la conexión usando los valores que hay en pantalla.
     *
     * @return string
     */
    private function processTestConnection()
    {
        $url = $this->getPostedValue(self::CONFIG_URL);
        $submittedApiKey = $this->getPostedValue(self::CONFIG_API_KEY);

        $credentials = new CatalogIaCredentials();
        $storedApiKey = $credentials->decrypt((string) Configuration::get(self::CONFIG_API_KEY));
        $apiKeyToTest = $this->resolveApiKey($submittedApiKey, $storedApiKey, $credentials);

        // La prueba no guarda nada: solo se conserva la clave tecleada en el
        // formulario (enmascarada) para poder reintentar o corregirla.
        $this->apiKeyForDisplay = $apiKeyToTest;

        $errors = $this->validateCredentials($url, $apiKeyToTest);
        if (!empty($errors)) {
            return $this->renderErrors($errors);
        }

        $client = new CatalogIaApiClient();
        $result = $client->getStatus($url, $apiKeyToTest);

        if (!empty($result['success'])) {
            return $this->renderSuccessResult(isset($result['data']) ? $result['data'] : array());
        }

        return $this->displayError(
            $this->trans('No se pudo conectar con el servicio:', array(), self::TRANSLATION_DOMAIN)
            . ' '
            . htmlspecialchars($this->getApiErrorMessage($result), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Devuelve un valor del formulario como cadena segura.
     *
     * @param string $key
     *
     * @return string
     */
    private function getPostedValue($key)
    {
        $value = Tools::getValue($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Valida la URL y la clave enviadas desde el formulario.
     *
     * @param string $url
     * @param string $apiKey
     *
     * @return array
     */
    private function validateCredentials($url, $apiKey)
    {
        $errors = array();

        if ($url === '') {
            $errors[] = $this->trans(
                'La URL del servicio es obligatoria.',
                array(),
                self::TRANSLATION_DOMAIN
            );
        } elseif (!CatalogIaApiClient::isSecureUrl($url)) {
            $errors[] = $this->trans(
                'La URL debe ser válida y utilizar HTTPS.',
                array(),
                self::TRANSLATION_DOMAIN
            );
        }

        if ($apiKey === '') {
            $errors[] = $this->trans(
                'La Clave API es obligatoria.',
                array(),
                self::TRANSLATION_DOMAIN
            );
        }

        return $errors;
    }

    /**
     * Devuelve la clave real a partir del valor del campo.
     *
     * El campo muestra una máscara: si coincide con la de la clave guardada se
     * usa esa; si coincide con la del último intento (enviado en el campo
     * oculto cifrado) se usa la tecleada, de modo que probar y luego guardar
     * funciona sin volver a escribirla.
     *
     * @param string                $submittedApiKey
     * @param string                $storedApiKey
     * @param CatalogIaCredentials  $credentials
     *
     * @return string
     */
    private function resolveApiKey($submittedApiKey, $storedApiKey, CatalogIaCredentials $credentials)
    {
        if ($submittedApiKey === '') {
            return '';
        }

        // El borrador (último intento) tiene prioridad: es lo que el usuario
        // acaba de teclear y probar si su máscara coincide con la mostrada.
        $draftApiKey = $this->getDraftApiKey($credentials);
        if ($draftApiKey !== '' && $submittedApiKey === $credentials->mask($draftApiKey)) {
            return $draftApiKey;
        }

        if ($storedApiKey !== '' && $submittedApiKey === $credentials->mask($storedApiKey)) {
            return $storedApiKey;
        }

        return $submittedApiKey;
    }

    /**
     * Recupera la clave tecleada en el intento anterior desde el campo oculto.
     *
     * @param CatalogIaCredentials $credentials
     *
     * @return string
     */
    private function getDraftApiKey(CatalogIaCredentials $credentials)
    {
        $draft = Tools::getValue(self::CONFIG_API_KEY_DRAFT);

        if (!is_string($draft) || strpos($draft, self::DRAFT_CIPHER_PREFIX) !== 0) {
            return '';
        }

        $apiKey = $credentials->decrypt($draft);

        return is_string($apiKey) ? $apiKey : '';
    }

    /**
     * Construye el mensaje de confirmación cuando la conexión es correcta.
     *
     * @param array $data
     *
     * @return string
     */
    private function renderSuccessResult(array $data)
    {
        $online = !empty($data['online']);
        $version = isset($data['version']) ? (string) $data['version'] : '-';
        $comercioId = isset($data['comercio_id']) ? (string) $data['comercio_id'] : '-';

        $version = htmlspecialchars($version, ENT_QUOTES, 'UTF-8');
        $comercioId = htmlspecialchars($comercioId, ENT_QUOTES, 'UTF-8');

        if (!$online) {
            return $this->displayWarning(
                sprintf(
                    $this->trans(
                        'El servicio respondió correctamente pero se encuentra fuera de línea (versión %s, comercio %s).',
                        array(),
                        self::TRANSLATION_DOMAIN
                    ),
                    $version,
                    $comercioId
                )
            );
        }

        return $this->displayConfirmation(
            sprintf(
                $this->trans(
                    'Conexión correcta. Servicio en línea (versión %s, comercio %s).',
                    array(),
                    self::TRANSLATION_DOMAIN
                ),
                $version,
                $comercioId
            )
        );
    }

    /**
     * Traduce el resultado de error devuelto por el cliente API.
     *
     * @param array $result
     *
     * @return string
     */
    private function getApiErrorMessage(array $result)
    {
        $code = isset($result['code']) ? $result['code'] : '';

        switch ($code) {
            case 'invalid_url':
                return $this->trans(
                    'La URL del servicio no es válida o no utiliza HTTPS.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'invalid_key':
                return $this->trans(
                    'La Clave API no es válida.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'curl_missing':
                return $this->trans(
                    'La extensión cURL de PHP no está disponible en el servidor.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'curl_init':
                return $this->trans(
                    'No se pudo inicializar la conexión con el servicio.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'timeout':
                return $this->trans(
                    'El servicio ha tardado demasiado en responder. Inténtelo de nuevo más tarde.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'network':
                return $this->trans(
                    'No se pudo establecer la conexión con el servicio.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'too_large':
                return $this->trans(
                    'La respuesta del servicio es demasiado grande.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'invalid_response':
                return $this->trans(
                    'El servicio devolvió una respuesta no válida.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
            case 'http':
                return sprintf(
                    $this->trans(
                        'El servicio respondió con el código HTTP %d.',
                        array(),
                        self::TRANSLATION_DOMAIN
                    ),
                    isset($result['http_code']) ? (int) $result['http_code'] : 0
                );
            case 'api':
            default:
                if (!empty($result['message'])) {
                    return (string) $result['message'];
                }

                if (!empty($result['api_code'])) {
                    return sprintf(
                        $this->trans(
                            'El servicio devolvió el error "%s".',
                            array(),
                            self::TRANSLATION_DOMAIN
                        ),
                        (string) $result['api_code']
                    );
                }

                return $this->trans(
                    'El servicio devolvió un error desconocido.',
                    array(),
                    self::TRANSLATION_DOMAIN
                );
        }
    }

    /**
     * Pinta una lista de errores de validación.
     *
     * @param array $errors
     *
     * @return string
     */
    private function renderErrors(array $errors)
    {
        $html = '';

        foreach ($errors as $error) {
            $html .= $this->displayError($error);
        }

        return $html;
    }

    /**
     * Genera el formulario de configuración.
     *
     * @return string
     */
    private function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        // Vacío a propósito: si se define un `submit_action`, el template de
        // HelperForm añade un <input type="hidden" name="submit_action"> que
        // se envía junto al botón pulsado. Así "Probar conexión" guardaría la
        // configuración. Con la cadena vacía solo se envía el botón pulsado.
        $helper->submit_action = '';
        $helper->currentIndex = $this->buildFormAction();
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $values = $this->getConfigFormValues();

        $helper->tpl_vars = array(
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm($values)));
    }

    /**
     * Construye la URL de envío del formulario, forzando HTTPS si la
     * petición actual ya es segura (evita avisos de formulario no seguro
     * en instalaciones detrás de proxy inverso).
     *
     * @return string
     */
    private function buildFormAction()
    {
        $action = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;

        if ($this->isHttpsRequest()) {
            $action = preg_replace('#^http://#i', 'https://', $action);
        }

        return $action;
    }

    /**
     * Define los campos del formulario.
     *
     * @param array $values Valores a mostrar en el formulario.
     *
     * @return array
     */
    private function getConfigForm(array $values)
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Configuración', array(), self::TRANSLATION_DOMAIN),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans(
                            'URL del servicio Catálogo IA',
                            array(),
                            self::TRANSLATION_DOMAIN
                        ),
                        'name' => self::CONFIG_URL,
                        'required' => true,
                        'desc' => $this->trans(
                            'URL base del servicio. Solo se admiten conexiones HTTPS.',
                            array(),
                            self::TRANSLATION_DOMAIN
                        ),
                        'placeholder' => 'https://catalogo.ejemplo.com',
                    ),
                    array(
                        // `html` permite pintar un campo password con la
                        // máscara como valor (el template de HelperForm fuerza
                        // value="" en los password) y ocultar lo que se teclea.
                        'type' => 'html',
                        'label' => $this->trans('Clave API', array(), self::TRANSLATION_DOMAIN),
                        'name' => self::CONFIG_API_KEY,
                        'required' => true,
                        'html_content' => $this->buildApiKeyField($values),
                        'desc' => $this->trans(
                            'La clave se guarda cifrada y se muestra enmascarada. Deje el valor enmascarado para conservar la clave guardada o escriba una nueva para reemplazarla.',
                            array(),
                            self::TRANSLATION_DOMAIN
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Guardar', array(), self::TRANSLATION_DOMAIN),
                    'name' => 'submitCatalogIaPs',
                ),
                'buttons' => array(
                    array(
                        'type' => 'submit',
                        'title' => $this->trans('Probar conexión', array(), self::TRANSLATION_DOMAIN),
                        'name' => 'submitCatalogIaPsTest',
                        'icon' => 'process-icon-refresh',
                    ),
                ),
            ),
        );
    }

    /**
     * Pinta el campo de la Clave API.
     *
     * El campo es `password` por defecto (si no hay JavaScript, la clave
     * tecleada queda oculta). El JS lo convierte a `text` con
     * `-webkit-text-security` cuando el navegador lo soporta, para poder
     * mostrar la máscara en reposo y ocultar lo que se escribe.
     *
     * @param array $values
     *
     * @return string
     */
    private function buildApiKeyField(array $values)
    {
        $maskedKey = isset($values[self::CONFIG_API_KEY]) ? (string) $values[self::CONFIG_API_KEY] : '';
        $draftCipher = isset($values[self::CONFIG_API_KEY_DRAFT]) ? (string) $values[self::CONFIG_API_KEY_DRAFT] : '';
        $escapedMask = htmlspecialchars($maskedKey, ENT_QUOTES, 'UTF-8');

        $html = '<div class="input-group fixed-width-lg">'
            . '<span class="input-group-addon"><i class="icon-key"></i></span>'
            . '<input type="password"'
            . ' id="' . self::CONFIG_API_KEY . '"'
            . ' name="' . self::CONFIG_API_KEY . '"'
            . ' class="catalog-ia-ps-key"'
            . ' value="' . $escapedMask . '"'
            . ' data-catalog-ia-ps-mask="' . $escapedMask . '"'
            . ' autocomplete="off"'
            . ' required="required" />'
            . '</div>';

        if ($draftCipher !== '') {
            $html .= '<input type="hidden"'
                . ' name="' . self::CONFIG_API_KEY_DRAFT . '"'
                . ' value="' . htmlspecialchars($draftCipher, ENT_QUOTES, 'UTF-8') . '" />';
        }

        return $html;
    }

    /**
     * Rellena el formulario con los valores a mostrar.
     *
     * @return array
     */
    private function getConfigFormValues()
    {
        $credentials = new CatalogIaCredentials();
        $storedApiKey = $credentials->decrypt((string) Configuration::get(self::CONFIG_API_KEY));
        $apiKey = $this->apiKeyForDisplay !== null ? $this->apiKeyForDisplay : $storedApiKey;

        $url = Configuration::get(self::CONFIG_URL);
        if ($this->urlForDisplay !== null) {
            $url = $this->urlForDisplay;
        }

        // Solo se transporta la clave cifrada cuando difiere de la guardada
        // (último intento de prueba o guardado con error). Si el cifrado no
        // está disponible no se muestra la máscara del intento, para que nunca
        // se pueda acabar guardando una máscara como si fuese la clave.
        $draftCipher = '';
        if ($apiKey !== '' && $apiKey !== $storedApiKey) {
            $cipher = $credentials->encrypt($apiKey);
            if (strpos($cipher, self::DRAFT_CIPHER_PREFIX) === 0) {
                $draftCipher = $cipher;
            } else {
                $apiKey = $storedApiKey;
            }
        }

        return array(
            self::CONFIG_URL => $url === false ? '' : (string) $url,
            self::CONFIG_API_KEY => $credentials->mask($apiKey),
            self::CONFIG_API_KEY_DRAFT => $draftCipher,
        );
    }
}
