<?php
interface ProviderInterface {
    /**
     * Retorna um array de itens com as chaves esperadas pelo pipeline de importação:
     * cada item deve conter ao menos 'file_url' ou 'file_base64' e metadados opcionais.
     *
     * @param array $credentials Credenciais / parâmetros necessários para acessar o provedor
     * @return array
     */
    public function fetchFiles(array $credentials = []): array;
}
