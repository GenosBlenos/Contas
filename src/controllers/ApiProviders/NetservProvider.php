<?php
require_once __DIR__ . '/AbstractProvider.php';

class NetservProvider extends AbstractProvider {
    public function fetchFiles(array $credentials = []): array
    {
        // Netserv Telefonia — stub para baixar faturas
        // Expects: 'direct_urls' or 'download_page' or API endpoints in $credentials
        if (!empty($credentials['direct_urls']) && is_array($credentials['direct_urls'])) {
            $items = [];
            foreach ($credentials['direct_urls'] as $u) {
                $items[] = $this->makeItem(['file_url' => $u, 'modulo' => 'telefone']);
            }
            return $items;
        }

        if (!empty($credentials['api_endpoint'])) {
            $resp = $this->httpGet($credentials['api_endpoint'], $credentials['headers'] ?? []);
            if ($resp['code'] >= 200 && $resp['code'] < 300) {
                $data = json_decode($resp['body'], true);
                if (is_array($data)) {
                    $items = [];
                    foreach ($data as $d) {
                        // mapear conforme o formato da API real
                        $items[] = $this->makeItem([
                            'file_url' => $d['file_url'] ?? null,
                            'titulo' => $d['titulo'] ?? null,
                            'modulo' => 'telefone',
                            'mes_referencia' => $d['mes'] ?? null,
                            'ano_referencia' => $d['ano'] ?? null,
                            'codigo_instalacao' => $d['instalacao'] ?? null
                        ]);
                    }
                    return $items;
                }
            }
            throw new Exception('Falha ao consultar endpoint Netserv: ' . $resp['error']);
        }

        throw new Exception('Credenciais insuficientes para Netserv provider');
    }
}
