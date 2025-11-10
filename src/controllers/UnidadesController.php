<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../models/Unidade.php';

class UnidadesController {
    public function index($module = null) {
        $model = new Unidade();
        if ($module) {
            // return $model->where('modulo', $module);
        }
        return $model->all();
    }

    public function show($id) {
        $model = new Unidade();
        return $model->find($id);
    }

    private function isAddressDuplicate($endereco, $id = null) {
        $query = "SELECT id FROM unidades WHERE endereco = :endereco";
        $params = [':endereco' => $endereco];
    
        if ($id !== null) {
            $query .= " AND id != :id";
            $params[':id'] = $id;
        }
    
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return !empty($result);
    }

    public function store($data) {
        if ($this->isAddressDuplicate($data['endereco'])) {
            flashMessage('error', 'Este endereço já está cadastrado para outra unidade.');
            return false;
        }
        $model = new Unidade();
        return $model->create($data);
    }

    public function update($id, $data) {
        if ($this->isAddressDuplicate($data['endereco'], $id)) {
            flashMessage('error', 'Este endereço já está cadastrado para outra unidade.');
            return false;
        }
        $model = new Unidade();
        return $model->update($id, $data);
    }

    public function destroy($id) {
        $model = new Unidade();
        return $model->delete($id);
    }
}
