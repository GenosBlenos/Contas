<?php

class Report {
    private $db;
    private $data = [];
    private $filters = [];
    private $groupBy = [];
    private $orderBy = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    public function addFilter($field, $value, $operator = '=') {
        $this->filters[] = [
            'field' => $field,
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
    }

    public function addGroupBy($field) {
        $this->groupBy[] = $field;
        return $this;
    }

    public function addOrderBy($field, $direction = 'ASC') {
        $this->orderBy[] = [
            'field' => $field,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }

    public function generateSQL($table, $fields = ['*']) {
        $sql = "SELECT " . implode(', ', $fields) . " FROM {$table}";

        // Adiciona WHERE
        if (!empty($this->filters)) {
            $conditions = [];
            foreach ($this->filters as $filter) {
                $conditions[] = "{$filter['field']} {$filter['operator']} ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        // Adiciona GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // Adiciona ORDER BY
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = "{$order['field']} {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        return $sql;
    }

    public function execute($sql) {
        $stmt = $this->db->prepare($sql);
        
        if ($stmt) {
            if (!empty($this->filters)) {
                $types = '';
                $values = [];
                foreach ($this->filters as $filter) {
                    $types .= $this->getTypeForValue($filter['value']);
                    $values[] = $filter['value'];
                }
                $stmt->bind_param($types, ...$values);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        
        return [];
    }

    private function getTypeForValue($value) {
        if (is_int($value)) return 'i';
        if (is_double($value)) return 'd';
        if (is_string($value)) return 's';
        return 'b';
    }

    public function toCSV($data, $filename) {
        if (empty($data)) return false;

        $output = fopen('php://temp', 'r+');
        
        // Cabeçalho
        fputcsv($output, array_keys($data[0]));
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo $csv;
        return true;
    }

    public function toJSON($data) {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function toHTML($data) {
        if (empty($data)) return '<p>Nenhum dado encontrado.</p>';

        $html = '<table class="min-w-full divide-y divide-gray-200">';
        
        // Cabeçalho
        $html .= '<thead class="bg-gray-50"><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">';
            $html .= htmlspecialchars($header);
            $html .= '</th>';
        }
        $html .= '</tr></thead>';
        
        // Dados
        $html .= '<tbody class="bg-white divide-y divide-gray-200">';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">';
                $html .= htmlspecialchars($value);
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    public function calculateTotal($data, $field) {
        return array_sum(array_column($data, $field));
    }

    public function calculateAverage($data, $field) {
        $total = $this->calculateTotal($data, $field);
        return $total / count($data);
    }

    public function getDateRange($data, $dateField) {
        $dates = array_column($data, $dateField);
        return [
            'min' => min($dates),
            'max' => max($dates)
        ];
    }
}
