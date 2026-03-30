<?php
require_once __DIR__ . '/AbstractProvider.php';

class SaaeSaltoProvider extends AbstractProvider {
    /**
     * Exemplo de implementação: realiza login (se necessário) e busca PDFs disponíveis.
     * PARA USAR: preencher `$credentials` com as chaves esperadas (ex: login, password, download_page)
     */
    public function fetchFiles(array $credentials = []): array
    {
        // Este é um stub. Cada provedor requer integração específica.
        // Suportamos dois modos via credenciais:
        // - 'download_page' => uma URL pública/privada que contém links diretos para PDFs
        // - 'direct_urls' => array de URLs diretos para baixar

        $items = [];

        if (!empty($credentials['direct_urls']) && is_array($credentials['direct_urls'])) {
            foreach ($credentials['direct_urls'] as $u) {
                $items[] = $this->makeItem(['file_url' => $u, 'modulo' => 'agua']);
            }
            return $items;
        }

        if (!empty($credentials['download_page'])) {
            $resp = $this->httpGet($credentials['download_page'], $credentials['headers'] ?? []);
            if ($resp['code'] >= 200 && $resp['code'] < 300 && $resp['body']) {
                $body = $resp['body'];
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                if (@$dom->loadHTML($body)) {
                    $xpath = new DOMXPath($dom);
                    $nodes = $xpath->query('//a[contains(translate(@href, "PDF", "pdf"), ".pdf")]');
                    if ($nodes->length === 0) {
                        // fallback: find any <a> with .pdf in href
                        $nodes = $xpath->query('//a[contains(@href, ".pdf")]');
                    }
                    foreach ($nodes as $n) {
                        if (!($n instanceof DOMElement)) continue;
                        $href = $n->getAttribute('href');
                        if (!$href) continue;
                        $fileUrl = $this->resolveUrl($credentials['download_page'], $href);
                        $items[] = $this->makeItem(['file_url' => $fileUrl, 'modulo' => $credentials['modulo'] ?? 'agua']);
                    }
                }
                return $items;
            }
            throw new Exception('Falha ao acessar download_page: ' . $resp['error']);
        }

        // Suporte para chamar a API diretamente (ex: /api/Imoveis/BuscarCdcCpfCnpjResumo)
        if (!empty($credentials['api_base'])) {
            $endpoint = $credentials['endpoint'] ?? '/api/Imoveis/BuscarCdcCpfCnpjResumo';
            $url = rtrim($credentials['api_base'], '/') . '/' . ltrim($endpoint, '/');
            $payload = $credentials['payload'] ?? [];
            if (empty($payload)) {
                if (!empty($credentials['cpf_cnpj'])) $payload['cpfCnpj'] = $credentials['cpf_cnpj'];
                if (!empty($credentials['cdc'])) $payload['cdc'] = $credentials['cdc'];
            }
            $headers = $credentials['headers'] ?? [];
            $resp = $this->httpPostJson($url, $payload, $headers);
            if ($resp['code'] >= 200 && $resp['code'] < 300 && $resp['body']) {
                $data = json_decode($resp['body'], true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                    $results = [];
                    if (isset($data['results']) && is_array($data['results'])) $results = $data['results'];
                    elseif (isset($data[0]) && is_array($data)) $results = $data;
                    elseif (is_array($data)) $results = $data;

                    foreach ($results as $r) {
                        $fileUrl = $r['arquivo'] ?? $r['url'] ?? $r['link'] ?? $r['arquivoUrl'] ?? null;
                        $modulo = $credentials['modulo'] ?? 'agua';
                        $item = $this->makeItem(['modulo' => $modulo]);
                        if ($fileUrl) $item['file_url'] = $fileUrl;
                        $item['codigo_instalacao'] = $r['codigo'] ?? $r['codigoInstalacao'] ?? $r['cdc'] ?? null;
                        $item['titulo'] = $r['nome'] ?? $r['titulo'] ?? null;
                        $items[] = $item;
                    }
                    return $items;
                }
            }
            throw new Exception('Falha ao acessar API: ' . $resp['error']);
        }

        throw new Exception('Credenciais insuficientes para SAAE SALTO provider');
    }
}
