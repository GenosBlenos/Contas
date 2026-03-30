<?php
require_once __DIR__ . '/ProviderInterface.php';

abstract class AbstractProvider implements ProviderInterface {
    protected function httpGet(string $url, array $headers = [], int $timeout = 60) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['body' => $resp, 'code' => $code, 'error' => $err];
    }

    protected function httpPost(string $url, array $postFields = [], array $headers = [], int $timeout = 60) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['body' => $resp, 'code' => $code, 'error' => $err];
    }

    /**
     * Send a JSON POST request. Encodes $data as JSON and sets Content-Type header.
     */
    protected function httpPostJson(string $url, $data, array $headers = [], int $timeout = 60) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        $json = is_string($data) ? $data : json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $hasContentType = false;
        foreach ($headers as $h) {
            if (stripos($h, 'content-type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            $headers[] = 'Content-Type: application/json';
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['body' => $resp, 'code' => $code, 'error' => $err];
    }

    /**
     * Resolve a relative URL against a base URL.
     */
    protected function resolveUrl(string $base, string $rel): string {
        if (trim($rel) === '') return $base;
        // absolute URL
        if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
        // protocol-relative
        if (substr($rel, 0, 2) === '//') {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'http';
            return $scheme . ':' . $rel;
        }
        $bp = parse_url($base);
        $scheme = $bp['scheme'] ?? 'http';
        $host = $bp['host'] ?? '';
        $port = isset($bp['port']) ? ':' . $bp['port'] : '';
        $basePath = $bp['path'] ?? '/';
        if ($rel[0] === '/') {
            return $scheme . '://' . $host . $port . $rel;
        }
        // remove filename from path
        $basePath = preg_replace('#/[^/]*$#', '/', $basePath);
        $abs = $scheme . '://' . $host . $port . $basePath . $rel;
        // normalize
        $abs = preg_replace('#(/\.\/)#', '/', $abs);
        while (preg_match('#/[^/]+/\.\.#', $abs)) {
            $abs = preg_replace('#/[^/]+/\.\.#', '/', $abs);
        }
        return $abs;
    }

    // Helper para retornar um item padrão esperável pelo pipeline
    protected function makeItem(array $overrides = []) {
        return array_merge([
            'file_url' => null,
            'file_base64' => null,
            'titulo' => null,
            'modulo' => null,
            'mes_referencia' => null,
            'ano_referencia' => null,
            'codigo_instalacao' => null,
            'numero_fatura' => null,
            'vencimento' => null,
            'total_a_pagar' => null
        ], $overrides);
    }
}
