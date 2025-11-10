<?php

class Validator {
    private $errors = [];
    private $data = [];
    private $rules = [];

    public function __construct(array $data, array $rules) {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate() {
        foreach ($this->rules as $field => $rules) {
            foreach ($rules as $rule => $parameter) {
                $this->applyRule($field, $rule, $parameter);
            }
        }

        return empty($this->errors);
    }

    private function applyRule($field, $rule, $parameter) {
        $value = $this->data[$field] ?? null;

        switch ($rule) {
            case 'required':
                if ($parameter && empty($value)) {
                    $this->addError($field, 'Este campo é obrigatório');
                }
                break;

            case 'min':
                if (strlen($value) < $parameter) {
                    $this->addError($field, "Mínimo de {$parameter} caracteres");
                }
                break;

            case 'max':
                if (strlen($value) > $parameter) {
                    $this->addError($field, "Máximo de {$parameter} caracteres");
                }
                break;

            case 'email':
                if ($parameter && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'E-mail inválido');
                }
                break;

            case 'numeric':
                if ($parameter && !is_numeric($value)) {
                    $this->addError($field, 'Deve ser um número');
                }
                break;

            case 'date':
                if ($parameter && !strtotime($value)) {
                    $this->addError($field, 'Data inválida');
                }
                break;

            case 'decimal':
                if ($parameter && !preg_match('/^\d*\.?\d{0,2}$/', $value)) {
                    $this->addError($field, 'Valor decimal inválido');
                }
                break;

            case 'in':
                if (is_array($parameter) && !in_array($value, $parameter)) {
                    $this->addError($field, 'Valor inválido');
                }
                break;
        }
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getFirstError($field) {
        return $this->errors[$field][0] ?? null;
    }

    public function getAllErrors() {
        $allErrors = [];
        foreach ($this->errors as $fieldErrors) {
            $allErrors = array_merge($allErrors, $fieldErrors);
        }
        return $allErrors;
    }
}
