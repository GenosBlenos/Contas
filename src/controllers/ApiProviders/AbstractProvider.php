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
