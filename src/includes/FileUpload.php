<?php

class FileUpload {
    private $allowedTypes;
    private $maxSize;
    private $uploadPath;
    private $errors = [];

    public function __construct($uploadPath, $allowedTypes = [], $maxSize = 5242880) {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;

        if (!file_exists($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    public function upload($file, $newFilename = null) {
        if (!$this->validate($file)) {
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $newFilename ? $newFilename . '.' . $extension : $this->generateFilename($file['name']);
        $destination = $this->uploadPath . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $filename;
        }

        $this->errors[] = 'Erro ao mover o arquivo';
        return false;
    }

    private function validate($file) {
        // Verificar erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }

        // Verificar tamanho
        if ($file['size'] > $this->maxSize) {
            $this->errors[] = 'Arquivo muito grande. Tamanho máximo: ' . $this->formatSize($this->maxSize);
            return false;
        }

        // Verificar tipo
        if (!empty($this->allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedTypes)) {
                $this->errors[] = 'Tipo de arquivo não permitido';
                return false;
            }
        }

        return true;
    }

    private function generateFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    private function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Arquivo excede o limite definido no php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Arquivo excede o limite definido no formulário';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incompleto';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo enviado';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Pasta temporária não encontrada';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Falha ao gravar arquivo';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload interrompido por extensão';
            default:
                return 'Erro desconhecido';
        }
    }

    private function formatSize($size) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($size >= 1024 && $i < 4) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getAllowedTypes() {
        return $this->allowedTypes;
    }

    public function getMaxSize() {
        return $this->maxSize;
    }

    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }

    public function setMaxSize($size) {
        $this->maxSize = $size;
    }

    public function deleteFile($filename) {
        $file = $this->uploadPath . '/' . $filename;
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
}
