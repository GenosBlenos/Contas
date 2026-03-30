<?php
require_once __DIR__ . '/../models/Documento.php';
require_once __DIR__ . '/../includes/FileUpload.php';

class DocumentosController {
    public function index($module = null, $page = 1, $perPage = 15) {
        $model = new Documento();
        if ($module) {
            $total = $model->countByModule($module);
            $page = max(1, (int)$page);
            $perPage = max(1, (int)$perPage);
            $offset = ($page - 1) * $perPage;
            $items = $model->findByModule($module, $perPage, $offset);
            return [
                'items' => $items,
                'total' => $total
            ];
        }
        // fallback: return all documents when no module specified
        return $model->all();
    }

    public function show($id) {
        $model = new Documento();
        return $model->find($id);
    }

    public function store($data, $file) {
        $uploader = new FileUpload(__DIR__ . '/../../uploads', ['application/pdf', 'image/jpeg', 'image/png']);

        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadedFilename = $uploader->upload($file);
            if ($uploadedFilename) {
                $data['arquivo'] = $uploadedFilename;
                $model = new Documento();
                return $model->create($data);
            }
        }
        // Se não houver arquivo ou o upload falhar
        return false;
    }

    public function update($id, $data, $file) {
        $model = new Documento();
        $uploader = new FileUpload(__DIR__ . '/../../uploads');

        // Se um novo arquivo foi enviado, processa o upload
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $oldDocument = $model->find($id);
            $uploadedFilename = $uploader->upload($file);

            if ($uploadedFilename) {
                $data['arquivo'] = $uploadedFilename;
                // Exclui o arquivo antigo se o novo foi salvo com sucesso
                if ($oldDocument && !empty($oldDocument['arquivo'])) {
                    $uploader->deleteFile($oldDocument['arquivo']);
                }
            } else {
                return false; // Falha no upload do novo arquivo
            }
        }

        return $model->update($id, $data);
    }

    public function destroy($id) {
        $model = new Documento();
        $documento = $model->find($id);

        if ($documento && $model->delete($id)) {
            $uploader = new FileUpload(__DIR__ . '/../../uploads');
            return $uploader->deleteFile($documento['arquivo']);
        }
        return false;
    }

    /**
     * Renomeia um arquivo físico e atualiza no banco de dados
     */
    public function renomearArquivo($id, $novoNome) {
        $model = new Documento();
        $documento = $model->find($id);
        
        if (!$documento) {
            return false;
        }

        $arquivoAntigo = $documento['arquivo'];
        $extensao = pathinfo($arquivoAntigo, PATHINFO_EXTENSION);
        $novoNomeArquivo = $novoNome . '.' . $extensao;

        $caminhoAntigo = __DIR__ . '/../../uploads/' . $arquivoAntigo;
        $caminhoNovo = __DIR__ . '/../../uploads/' . $novoNomeArquivo;

        // Verifica se o arquivo antigo existe
        if (!file_exists($caminhoAntigo)) {
            return false;
        }

        // Renomear o arquivo físico
        if (rename($caminhoAntigo, $caminhoNovo)) {
            // Atualizar no banco
            return $model->update($id, ['arquivo' => $novoNomeArquivo]);
        }

        return false;
    }

    /**
     * Atualiza os dados extras da fatura
     */
    public function atualizarDadosFatura($id, $dados) {
        $model = new Documento();
        
        // Prepara os dados para atualização
        $dadosAtualizacao = [];
        
        if (isset($dados['modulo'])) {
            $dadosAtualizacao['modulo'] = $dados['modulo'];
        }
        if (isset($dados['mes_referencia'])) {
            $dadosAtualizacao['mes_referencia'] = $dados['mes_referencia'];
        }
        if (isset($dados['ano_referencia'])) {
            $dadosAtualizacao['ano_referencia'] = $dados['ano_referencia'];
        }
        if (isset($dados['codigo_instalacao'])) {
            $dadosAtualizacao['codigo_instalacao'] = $dados['codigo_instalacao'];
        }
        if (isset($dados['numero_fatura'])) {
            $dadosAtualizacao['numero_fatura'] = $dados['numero_fatura'];
        }
        if (isset($dados['vencimento'])) {
            $dadosAtualizacao['vencimento'] = $dados['vencimento'];
        }
        if (isset($dados['total_a_pagar'])) {
            $dadosAtualizacao['total_a_pagar'] = $dados['total_a_pagar'];
        }

        // Se não há dados para atualizar, retorna true
        if (empty($dadosAtualizacao)) {
            return true;
        }

        return $model->update($id, $dadosAtualizacao);
    }

    /**
     * Obtém os dados extras da fatura
     */
    public function obterDadosFatura($id) {
        $model = new Documento();
        $documento = $model->find($id);
        
        if (!$documento) {
            return [];
        }

        // Retorna apenas os campos relacionados à fatura
        return [
            'modulo' => $documento['modulo'] ?? null,
            'mes_referencia' => $documento['mes_referencia'] ?? null,
            'ano_referencia' => $documento['ano_referencia'] ?? null,
            'codigo_instalacao' => $documento['codigo_instalacao'] ?? null,
            'numero_fatura' => $documento['numero_fatura'] ?? null,
            'vencimento' => $documento['vencimento'] ?? null,
            'total_a_pagar' => $documento['total_a_pagar'] ?? null
        ];
    }

    /**
     * Gera automaticamente o nome do arquivo baseado nos dados da fatura
     */
    public function gerarNomeAutomatico($dadosFatura) {
        $modulo = $dadosFatura['modulo'] ?? 'documento';
        $mes = $this->extrairMes($dadosFatura['mes_referencia'] ?? '');
        $ano = $dadosFatura['ano_referencia'] ?? date('Y');
        $instalacao = $dadosFatura['codigo_instalacao'] ?? $dadosFatura['numero_fatura'] ?? '000000';
        
        // Limpa os valores para uso em nome de arquivo
        $modulo = preg_replace('/[^a-zA-Z0-9-_]/', '_', $modulo);
        $mes = preg_replace('/[^a-zA-Z0-9-_]/', '_', $mes);
        $ano = preg_replace('/[^a-zA-Z0-9-_]/', '_', $ano);
        $instalacao = preg_replace('/[^a-zA-Z0-9-_]/', '_', $instalacao);
        
        return $modulo . '_' . $mes . '_' . $ano . '_' . $instalacao;
    }

    /**
     * Extrai apenas a parte do mês da referência
     */
    private function extrairMes($mesReferencia) {
        if (!$mesReferencia) return 'mes';
        
        // Remove espaços e divide por barra
        $partes = explode('/', trim($mesReferencia));
        if (count($partes) >= 1) {
            return $partes[0];
        }
        
        return $mesReferencia;
    }
}