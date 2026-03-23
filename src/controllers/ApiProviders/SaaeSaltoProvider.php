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
                // Implementar parsing HTML para extrair links de PDF conforme o site real.
                // Aqui retornamos vazio com instrução para o usuário completar.
                // Exemplo: usar DOMDocument / DOMXPath para buscar <a href="*.pdf">.
                return $items; // vazio até o parsing ser implementado
            }
            throw new Exception('Falha ao acessar download_page: ' . $resp['error']);
        }

        throw new Exception('Credenciais insuficientes para SAAE SALTO provider');
    }
}
