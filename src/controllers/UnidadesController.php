<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../models/Unidade.php';


if (!function_exists('flashMessage')) {
    function flashMessage($type, $message) {
        error_log("Flash [$type]: $message");
    }
}

class UnidadesController
{
    public function index($search = '', $page = 1, $limit = 10) {
        
        // 1. Obter a conexão PDO
        $pdo = Database::getInstance()->getConnection();

        // 2. Calcular o offset para a consulta
        $offset = ($page - 1) * $limit;

        // 3. Preparar a cláusula WHERE para a pesquisa
        $searchParams = []; // Parâmetros para a cláusula WHERE
        $whereClause = "";

        if (!empty($search)) {
            $whereClause = " WHERE (nome LIKE ? OR endereco LIKE ? OR responsavel LIKE ?)";
            $searchTerm = "%{$search}%";
            $searchParams = [$searchTerm, $searchTerm, $searchTerm];
        }

        // 4. Obter o TOTAL de registros (para calcular o total de páginas)
        $sqlTotal = "SELECT COUNT(*) FROM unidades" . $whereClause;
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute($searchParams);
        $total_registros = $stmtTotal->fetchColumn();
        
        $total_pages = $total_registros > 0 ? ceil($total_registros / $limit) : 1;


        // 5. Obter os registros da PÁGINA ATUAL (com LIMIT e OFFSET)
        $sqlData = "SELECT * FROM unidades" . $whereClause . " LIMIT ? OFFSET ?";
        
        $stmtData = $pdo->prepare($sqlData);

        $paramIndex = 1;
        foreach ($searchParams as $param) {
            $stmtData->bindValue($paramIndex++, $param, PDO::PARAM_STR);
        }

        $stmtData->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
        $stmtData->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);

        $stmtData->execute();
        $registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        // 6. Retornar os dados
        return [
            'registros' => $registros,
            'total_pages' => (int)$total_pages
        ];
    }
    
    public function show($id)
    {
        $model = new Unidade();
        return $model->find($id);
    }

    private function isAddressDuplicate($endereco, $id = null)
    {
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

    public function store($data)
    {
        if (empty($data['endereco'])) {
            flashMessage('error', 'O campo endereço é obrigatório.');
            return false;
        }
        if ($this->isAddressDuplicate($data['endereco'])) {
            flashMessage('error', 'Este endereço já está cadastrado para outra unidade.');
            return false;
        }
        $model = new Unidade();
        return $model->create($data);
    }

    public function update($id, $data)
    {
        if (empty($data['endereco'])) {
            flashMessage('error', 'O campo endereço é obrigatório.');
            return false;
        }
        if ($this->isAddressDuplicate($data['endereco'], $id)) {
            flashMessage('error', 'Este endereço já está cadastrado para outra unidade.');
            return false;
        }
        $model = new Unidade();
        return $model->update($id, $data);
    }

    public function destroy($id)
    {
        $model = new Unidade();
        return $model->delete($id);
    }
}
?>