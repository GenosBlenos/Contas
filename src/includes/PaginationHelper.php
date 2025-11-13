<?php
/**
 * PaginationHelper - Utilitário para gerenciar paginação
 */

class PaginationHelper {
    private $total_items;
    private $items_per_page;
    private $current_page;
    private $total_pages;

    public function __construct($total_items, $items_per_page = 10, $current_page = 1) {
        $this->total_items = (int)$total_items;
        $this->items_per_page = (int)$items_per_page;
        $this->current_page = max(1, (int)$current_page);
        $this->total_pages = $this->total_items > 0 ? ceil($this->total_items / $this->items_per_page) : 1;
        
        // Validar que página atual não ultrapasse o total
        if ($this->current_page > $this->total_pages) {
            $this->current_page = $this->total_pages;
        }
    }

    /**
     * Obter o offset para usar em LIMIT/OFFSET
     */
    public function getOffset() {
        return ($this->current_page - 1) * $this->items_per_page;
    }

    /**
     * Obter o limite para usar em LIMIT
     */
    public function getLimit() {
        return $this->items_per_page;
    }

    /**
     * Obter página atual
     */
    public function getCurrentPage() {
        return $this->current_page;
    }

    /**
     * Obter total de páginas
     */
    public function getTotalPages() {
        return $this->total_pages;
    }

    /**
     * Obter total de itens
     */
    public function getTotalItems() {
        return $this->total_items;
    }

    /**
     * Verificar se há próxima página
     */
    public function hasNextPage() {
        return $this->current_page < $this->total_pages;
    }

    /**
     * Verificar se há página anterior
     */
    public function hasPreviousPage() {
        return $this->current_page > 1;
    }

    /**
     * Gerar URLs com parâmetros de paginação
     */
    public static function buildQueryParams($new_params = []) {
        $params = array_merge($_GET, $new_params);
        return http_build_query($params);
    }

    /**
     * Obter intervalo de páginas a exibir
     * @param int $window Número de páginas a cada lado da página atual
     */
    public function getPaginationRange($window = 2) {
        $start = max(1, $this->current_page - $window);
        $end = min($this->total_pages, $this->current_page + $window);

        // Se estamos no início, mostrar mais páginas no final
        if ($start == 1) {
            $end = min($this->total_pages, 1 + ($window * 2));
        }
        // Se estamos no final, mostrar mais páginas no início
        if ($end == $this->total_pages) {
            $start = max(1, $this->total_pages - ($window * 2));
        }

        return [
            'start' => $start,
            'end' => $end,
            'has_previous_gap' => $start > 1,
            'has_next_gap' => $end < $this->total_pages
        ];
    }
}
?>
