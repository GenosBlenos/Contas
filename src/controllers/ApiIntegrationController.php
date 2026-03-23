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
    public function syncFromApi(string $apiUrl, ?string $apiKey = null): array
    {
        $result = ['success' => [], 'errors' => []];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($apiKey) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$apiKey}"]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            $result['errors'][] = "Falha ao consultar API: HTTP {$httpCode} - {$curlErr}";
            return $result;
        }

        $items = json_decode($response, true);
        if (!is_array($items)) {
            $result['errors'][] = 'Resposta da API não é um array JSON válido.';
            return $result;
        }

        foreach ($items as $idx => $item) {
            try {
                // Determinar conteúdo do arquivo
                $content = null;
                $extension = 'pdf';

                if (!empty($item['file_base64'])) {
                    $content = base64_decode($item['file_base64']);
                } elseif (!empty($item['file_url'])) {
                    // Baixa via curl para maior controle
                    $fileCh = curl_init();
                    curl_setopt($fileCh, CURLOPT_URL, $item['file_url']);
                    curl_setopt($fileCh, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($fileCh, CURLOPT_TIMEOUT, 120);
                    if ($apiKey) {
                        curl_setopt($fileCh, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$apiKey}"]);
                    }
                    $content = curl_exec($fileCh);
                    $code = curl_getinfo($fileCh, CURLINFO_HTTP_CODE);
                    $fileErr = curl_error($fileCh);
                    curl_close($fileCh);

                    if ($content === false || $code < 200 || $code >= 300) {
                        throw new Exception("Falha ao baixar arquivo (index {$idx}): HTTP {$code} - {$fileErr}");
                    }

                    // tentar detectar extensão a partir do URL
                    $pathInfo = pathinfo(parse_url($item['file_url'], PHP_URL_PATH) ?? '');
                    if (!empty($pathInfo['extension'])) {
                        $extension = strtolower($pathInfo['extension']);
                    }
                } else {
                    throw new Exception('Item sem arquivo (file_url ou file_base64)');
                }

                // Preparar dados para gerar nome
                $dadosFatura = [
                    'modulo' => $item['modulo'] ?? 'documento',
                    'mes_referencia' => $item['mes_referencia'] ?? '',
                    'ano_referencia' => $item['ano_referencia'] ?? date('Y'),
                    'codigo_instalacao' => $item['codigo_instalacao'] ?? ($item['numero_fatura'] ?? '000000')
                ];

                $baseName = $this->documentosController->gerarNomeAutomatico($dadosFatura);
                $safeBaseName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $baseName);
                $finalFilename = $safeBaseName . '.' . $extension;

                // Se já existir, acrescentar sufixo incremental
                $i = 1;
                $targetPath = $this->uploadsPath . '/' . $finalFilename;
                while (file_exists($targetPath)) {
                    $finalFilename = $safeBaseName . '_' . $i . '.' . $extension;
                    $targetPath = $this->uploadsPath . '/' . $finalFilename;
                    $i++;
                }

                // Salva arquivo
                if (file_put_contents($targetPath, $content) === false) {
                    throw new Exception('Falha ao salvar arquivo em disco: ' . $targetPath);
                }

                // Persiste no banco na tabela documentos
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
                    // remover arquivo salvo se falha no banco
                    @unlink($targetPath);
                    throw new Exception('Falha ao inserir registro no banco de dados');
                }

                $result['success'][] = ['index' => $idx, 'file' => $finalFilename, 'id' => $insertId];
            } catch (Exception $e) {
                $result['errors'][] = ['index' => $idx, 'message' => $e->getMessage()];
            }
        }

        return $result;
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
            // Reutiliza a lógica de processamento existente transformando para JSON e chamando o mesmo fluxo
            $result = ['success' => [], 'errors' => []];

            foreach ($items as $idx => $item) {
                try {
                    // Reaproveita o código de syncFromApi para salvar cada item: adaptado aqui inline
                    $content = null;
                    $extension = 'pdf';

                    if (!empty($item['file_base64'])) {
                        $content = base64_decode($item['file_base64']);
                    } elseif (!empty($item['file_url'])) {
                        $fileCh = curl_init();
                        curl_setopt($fileCh, CURLOPT_URL, $item['file_url']);
                        curl_setopt($fileCh, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($fileCh, CURLOPT_TIMEOUT, 120);
                        $content = curl_exec($fileCh);
                        $code = curl_getinfo($fileCh, CURLINFO_HTTP_CODE);
                        $fileErr = curl_error($fileCh);
                        curl_close($fileCh);
                        if ($content === false || $code < 200 || $code >= 300) {
                            throw new Exception("Falha ao baixar arquivo (index {$idx}): HTTP {$code} - {$fileErr}");
                        }
                        $pathInfo = pathinfo(parse_url($item['file_url'], PHP_URL_PATH) ?? '');
                        if (!empty($pathInfo['extension'])) {
                            $extension = strtolower($pathInfo['extension']);
                        }
                    } else {
                        throw new Exception('Item sem arquivo (file_url ou file_base64)');
                    }

                    $dadosFatura = [
                        'modulo' => $item['modulo'] ?? 'documento',
                        'mes_referencia' => $item['mes_referencia'] ?? '',
                        'ano_referencia' => $item['ano_referencia'] ?? date('Y'),
                        'codigo_instalacao' => $item['codigo_instalacao'] ?? ($item['numero_fatura'] ?? '000000')
                    ];

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
                        @unlink($targetPath);
                        throw new Exception('Falha ao inserir registro no banco de dados');
                    }

                    $result['success'][] = ['index' => $idx, 'file' => $finalFilename, 'id' => $insertId];
                } catch (Exception $e) {
                    $result['errors'][] = ['index' => $idx, 'message' => $e->getMessage()];
                }
            }

            return $result;
        } catch (Exception $e) {
            return ['success' => [], 'errors' => [$e->getMessage()]];
        }
    }
}
