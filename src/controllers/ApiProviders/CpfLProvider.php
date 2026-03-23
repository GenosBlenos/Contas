<?php
require_once __DIR__ . '/AbstractProvider.php';

class CpfLProvider extends AbstractProvider {
    public function fetchFiles(array $credentials = []): array
    {
        // CPFL SALTO — stub
        if (!empty($credentials['direct_urls']) && is_array($credentials['direct_urls'])) {
            $items = [];
            foreach ($credentials['direct_urls'] as $u) {
                $items[] = $this->makeItem(['file_url' => $u, 'modulo' => 'energia']);
            }
            return $items;
        }

        if (!empty($credentials['login_url']) && !empty($credentials['username']) && !empty($credentials['password'])) {
            // Alguns portais exigem autenticação e scraping; implementar conforme necessário.
            return [];
        }

        throw new Exception('Credenciais insuficientes para CPFL provider');
    }
}
