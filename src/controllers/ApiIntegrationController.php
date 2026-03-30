<?php
require_once __DIR__ . '/DocumentosController.php';
require_once __DIR__ . '/../models/Documento.php';
// provider adapters
require_once __DIR__ . '/ApiProviders/ProviderInterface.php';
require_once __DIR__ . '/ApiProviders/AbstractProvider.php';
require_once __DIR__ . '/ApiProviders/SaaeSaltoProvider.php';
require_once __DIR__ . '/ApiProviders/NetservProvider.php';
require_once __DIR__ . '/ApiProviders/CpfLProvider.php';
require_once __DIR__ . '/ApiProviders/BestfibraProvider.php';

class ApiIntegrationController {
    private $uploadsPath;
    private $documentosController;
    private $documentoModel;

    public function __construct()
    {
        $this->uploadsPath = __DIR__ . '/../../uploads';
        if (!file_exists($this->uploadsPath)) {
            mkdir($this->uploadsPath, 0755, true);
        }

        $this->documentosController = new DocumentosController();
        $this->documentoModel = new Documento();
    }

    /**
     * Sincroniza arquivos a partir de uma API que retorna um array de objetos JSON.
     * Cada item esperado pode conter: file_url OR file_base64, modulo, codigo_instalacao,
     * mes_referencia, ano_referencia, numero_fatura, vencimento, total_a_pagar
     *
     * @param string $apiUrl
     * @param string|null $apiKey
     * @return array Relatório com sucesso/erros
     */
    public function syncFromApi(string $apiUrl, ?string $apiKey = null, ?string $module = null): array
    {
        $resp = $this->fetchJson($apiUrl, $apiKey);
        if (!$resp['ok']) {
            return $this->formatResult([], ["Falha ao consultar API: HTTP {$resp['code']} - {$resp['error']}"]);
        }

        $items = json_decode($resp['body'], true);
        if (!is_array($items)) {
            return $this->formatResult([], ['Resposta da API não é um array JSON válido.']);
        }

        // If a module was provided, ensure items have the module set when missing
        if ($module !== null) {
            foreach ($items as &$it) {
                if (is_array($it) && empty($it['modulo'])) {
                    $it['modulo'] = $module;
                }
            }
            unset($it);
        }

        return $this->processItems($items, $apiKey);
    }

    /**
     * Fetch a JSON endpoint with optional bearer token
     */
    private function fetchJson(string $url, ?string $apiKey = null, int $timeout = 60): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $headers = [];
        if ($apiKey) $headers[] = "Authorization: Bearer {$apiKey}";
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => ($body !== false && $code >= 200 && $code < 300), 'code' => $code, 'body' => $body, 'error' => $err];
    }

    /**
     * Download a file URL with optional bearer token
     */
    private function downloadFile(string $url, ?string $apiKey = null, int $timeout = 120): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $headers = [];
        if ($apiKey) $headers[] = "Authorization: Bearer {$apiKey}";
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => ($body !== false && $code >= 200 && $code < 300), 'code' => $code, 'body' => $body, 'error' => $err];
    }

    /**
     * Standardize result format
     */
    private function formatResult(array $successes, array $errors): array
    {
        return [
            'processed' => count($successes) + count($errors),
            'success_count' => count($successes),
            'error_count' => count($errors),
            'success' => $successes,
            'errors' => $errors
        ];
    }

    /**
     * Process multiple items through the same pipeline
     */
    private function processItems(array $items, ?string $apiKey = null): array
    {
        $success = [];
        $errors = [];

        foreach ($items as $idx => $item) {
            try {
                // Determine content
                $content = null;
                $extension = 'pdf';

                if (!empty($item['file_base64'])) {
                    $content = base64_decode($item['file_base64']);
                } elseif (!empty($item['file_url'])) {
                    $d = $this->downloadFile($item['file_url'], $apiKey);
                    if (!$d['ok']) {
                        throw new Exception("Falha ao baixar arquivo (index {$idx}): HTTP {$d['code']} - {$d['error']}");
                    }
                    $content = $d['body'];
                    $pathInfo = pathinfo(parse_url($item['file_url'], PHP_URL_PATH) ?? '');
                    if (!empty($pathInfo['extension'])) $extension = strtolower($pathInfo['extension']);
                } else {
                    throw new Exception('Item sem arquivo (file_url ou file_base64)');
                }

                $dadosFatura = [
                    'modulo' => $item['modulo'] ?? 'documento',
                    'mes_referencia' => $item['mes_referencia'] ?? '',
                    'ano_referencia' => $item['ano_referencia'] ?? date('Y'),
                    'codigo_instalacao' => $item['codigo_instalacao'] ?? ($item['numero_fatura'] ?? '000000')
                ];

                $finalFilename = $this->saveContentToFile($content, $dadosFatura, $extension);

                $createData = [
                    'titulo' => $item['titulo'] ?? $finalFilename,
                    'arquivo' => $finalFilename,
                    'modulo' => $dadosFatura['modulo'],
                    'mes_referencia' => $dadosFatura['mes_referencia'],
                    'ano_referencia' => $dadosFatura['ano_referencia'],
                    'codigo_instalacao' => $dadosFatura['codigo_instalacao'],
                    'numero_fatura' => $item['numero_fatura'] ?? null,
                    'vencimento' => $item['vencimento'] ?? null,
                    'total_a_pagar' => $item['total_a_pagar'] ?? null
                ];

                $insertId = $this->documentoModel->create($createData);
                if ($insertId === false) {
                    @unlink($this->uploadsPath . '/' . $finalFilename);
                    throw new Exception('Falha ao inserir registro no banco de dados');
                }

                $success[] = ['index' => $idx, 'file' => $finalFilename, 'id' => $insertId];
            } catch (Exception $e) {
                $errors[] = ['index' => $idx, 'message' => $e->getMessage()];
            }
        }

        return $this->formatResult($success, $errors);
    }

    /**
     * Save binary content to uploads directory and return filename
     */
    private function saveContentToFile(string $content, array $dadosFatura, string $extension = 'pdf'): string
    {
        $baseName = $this->documentosController->gerarNomeAutomatico($dadosFatura);
        $safeBaseName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $baseName);
        $finalFilename = $safeBaseName . '.' . $extension;

        $i = 1;
        $targetPath = $this->uploadsPath . '/' . $finalFilename;
        while (file_exists($targetPath)) {
            $finalFilename = $safeBaseName . '_' . $i . '.' . $extension;
            $targetPath = $this->uploadsPath . '/' . $finalFilename;
            $i++;
        }

        if (file_put_contents($targetPath, $content) === false) {
            throw new Exception('Falha ao salvar arquivo em disco: ' . $targetPath);
        }

        return $finalFilename;
    }

    /**
     * Sincroniza usando um provider registrado (SAAE/Netserv/CPFL/Bestfibra).
     * @param string $providerName
     * @param array $credentials
     * @return array
     */
    public function syncFromProvider(string $providerName, array $credentials = []): array
    {
        $map = [
            'saae' => 'SaaeSaltoProvider',
            'saae_salto' => 'SaaeSaltoProvider',
            'netserv' => 'NetservProvider',
            'netserv_telefonia' => 'NetservProvider',
            'cpfl' => 'CpfLProvider',
            'cpfl_salto' => 'CpfLProvider',
            'bestfibra' => 'BestfibraProvider',
            'bestfibra_internet' => 'BestfibraProvider'
        ];

        $key = strtolower($providerName);
        if (!isset($map[$key])) {
            return ['success' => [], 'errors' => ["Provider desconhecido: {$providerName}"]];
        }

        $class = $map[$key];
        if (!class_exists($class)) {
            return ['success' => [], 'errors' => ["Classe de provider não encontrada: {$class}"]];
        }

        try {
            /** @var ProviderInterface $provider */
            $provider = new $class();
            $items = $provider->fetchFiles($credentials);
            if (!is_array($items)) {
                return $this->formatResult([], ['Provider retornou dados inválidos']);
            }
            return $this->processItems($items, null);
        } catch (Exception $e) {
            return $this->formatResult([], [$e->getMessage()]);
        }
    }
}
