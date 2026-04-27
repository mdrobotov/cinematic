<?php

require_once 'database.php';
require_once 'log.php';

class ApiHandler {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function handle() {
        $action = $_GET['action'] ?? '';
        try {
            switch ($action) {
                case 'getTableData':
                    return $this->getTableData();
                default:
                    throw new Exception("Неизвестный action: $action");
            }
        } catch (Exception $e) {
            return json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function getTableData() {
        $table = $_GET['table'] ?? '';
        $allowed = [
            'Movies',
            'Halls',
            'Seats',
            'Clients',
            'Sessions',
            'Tickets'
        ];
        if (!in_array($table, $allowed))
            throw new Exception("Недопустимая таблица");

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        $sort = $_GET['sort'] ?? '';
        $order = $_GET['order'] ?? 'ASC';
        $filters = json_decode($_GET['filters'] ?? '{}', true);

        // Получаем список столбцов таблицы
        $query = $this->db->query("PRAGMA table_info($table)");
        $columns = $query->fetchAll(PDO::FETCH_COLUMN, 1);

        $where = [];
        $params = [];
        foreach ($filters as $col => $val) {
            if (in_array($col, $columns) && $val != '') {
                $where[] = "$col LIKE ?";
                $params[] = "%$val%";
            }
        }
        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $orderSql = "";
        if ($sort && in_array($sort, $columns)) {
            $order = strtoupper($order) == 'DESC' ? 'DESC' : 'ASC';
            $orderSql = "ORDER BY $sort $order";
        }

        $sql = "SELECT * FROM $table $whereSql $orderSql LIMIT $limit OFFSET $offset";
        $query = $this->db->prepare($sql);
        $query->execute($params);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        // Общее количество для пагинации
        $countSql = "SELECT COUNT(*) FROM $table $whereSql";
        $countQuery = $this->db->prepare($countSql);
        $countQuery->execute($params);
        $total = $countQuery->fetchColumn();

        return json_encode([
            'success' => true,
            'data' => $rows,
            'total' => $total
        ]);
    }
}

?>