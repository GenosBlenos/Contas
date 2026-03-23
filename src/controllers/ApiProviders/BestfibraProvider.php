<?php
require_once __DIR__ . '/AbstractProvider.php';

class BestfibraProvider extends AbstractProvider {
    public function fetchFiles(array $credentials = []): array
    {
        // Bestfibra Internet — stub
        if (!empty($credentials['direct_urls']) && is_array($credentials['direct_urls'])) {
            $items = [];
            foreach ($credentials['direct_urls'] as $u) {
                $items[] = $this->makeItem(['file_url' => $u, 'modulo' => 'internet']);
            }
            return $items;
        }

        // Se houver uma API/endpoint público que retorne JSON com urls, suportar aqui
        if (!empty($credentials['api_endpoint'])) {
            $resp = $this->httpGet($credentials['api_endpoint'], $credentials['headers'] ?? []);
            if ($resp['code'] >= 200 && $resp['code'] < 300) {
                $data = json_decode($resp['body'], true);
                if (is_array($data)) {
                    $items = [];
                    foreach ($data as $d) {
                        $items[] = $this->makeItem(['file_url' => $d['file_url'] ?? null, 'modulo' => 'internet']);
                    }
                    return $items;
                }
            }
            throw new Exception('Falha ao consultar endpoint Bestfibra: ' . $resp['error']);
        }

        throw new Exception('Credenciais insuficientes para Bestfibra provider');
    }
}
