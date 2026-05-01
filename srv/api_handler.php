<?php

require_once 'database.php';
require_once 'log.php';

class ApiHandler {
    private $db;
    private $allowedTables = [
        'Movies',
        'Halls',
        'Seats',
        'Clients',
        'Sessions',
        'Tickets'
    ];

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function handle() {
        parse_str($_SERVER['QUERY_STRING'], $queryArgs);
        $action = $queryArgs['action'] ?? '';

        try {
            switch ($action) {
                case 'getTableData':
                    return $this->getTableData();
                case 'getRecord':
                    return $this->getRecord();
                case 'insertRecord':
                    return $this->insertRecord();
                case 'updateRecord':
                    return $this->updateRecord();
                case 'deleteRecord':
                    return $this->deleteRecord();
                case 'addHallWithSeats':
                    return $this->addHallWithSeats();
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

        if (!in_array($table, $this->allowedTables))
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

    // Вспомогательный метод для получения первичного ключа таблицы
    private function getPrimaryKey($table) {
        $query = $this->db->query("PRAGMA table_info($table)");
        $cols = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if ($col['pk'])
                return $col['name'];
        }
        return 'id';
    }

    private function getRecord() {
        $table = $_GET['table'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if (!in_array($table, $this->allowedTables) || $id <= 0)
            throw new Exception("Неверный запрос");

        $pk = $this->getPrimaryKey($table);
        $query = $this->db->prepare("SELECT * FROM $table WHERE $pk = ?");
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row)
            throw new Exception("Запись не найдена");

        return json_encode(['success' => true, 'data' => $row]);
    }

    private function insertRecord() {
        parse_str($_SERVER['QUERY_STRING'], $queryArgs);
        $table = $queryArgs['table'] ?? '';

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!in_array($table, $this->allowedTables) || empty($data))
            throw new Exception("Неверные данные");

        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(',:', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $query = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $query->bindValue(":$k", $v === '' ? null : $v);
        }
        $query->execute();
        $newId = $this->db->lastInsertId();

        return json_encode(['success' => true, 'id' => $newId]);
    }

    private function updateRecord() {
        parse_str($_SERVER['QUERY_STRING'], $queryArgs);
        $table = $queryArgs['table'] ?? '';
        $id = (int)($queryArgs['id'] ?? 0);

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!in_array($table, $this->allowedTables) || empty($data) || $id <= 0)
            throw new Exception("Неверные данные");

        $sets = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = :$k";
        }

        $pk = $this->getPrimaryKey($table);
        $sql = "UPDATE $table SET " . implode(',', $sets) . " WHERE $pk = :id";
        $query = $this->db->prepare($sql);

        foreach ($data as $k => $v) {
            $query->bindValue(":$k", $v === '' ? null : $v);
        }

        $query->bindValue(':id', $id);
        $query->execute();
        return json_encode(['success' => true]);
    }

    private function deleteRecord() {
        $table = $_GET['table'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if (!in_array($table, $this->allowedTables) || $id <= 0)
            throw new Exception("Неверный запрос");

        $pk = $this->getPrimaryKey($table);
        $query = $this->db->prepare("DELETE FROM $table WHERE $pk = ?");
        $query->execute([$id]);
        return json_encode(['success' => true]);
    }

    private function addHallWithSeats() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data)
            throw new Exception("Нет данных для создания зала");

        $hallNumber = $data['hall_number'] ?? '';
        $hallType = $data['hall_type'] ?? 'Standard';
        $description = $data['description'] ?? '';
        $rows = (int)($data['rows'] ?? 0);
        $seatsPerRow = (int)($data['seats_per_row'] ?? 0);

        if (empty($hallNumber) || $rows <= 0 || $seatsPerRow <= 0)
            throw new Exception("Неверные данные");

        $totalSeats = $rows * $seatsPerRow;

        $this->db->beginTransaction();
        try {
            // Вставка зала
            $query = $this->db->prepare(
                "INSERT INTO Halls (hall_number, total_seats, hall_type, description)
                VALUES (?, ?, ?, ?)"
            );
            $query->execute([$hallNumber, $totalSeats, $hallType, $description]);
            $hallId = $this->db->lastInsertId();
            
            // Вставка мест
            $insertSeat = $this->db->prepare(
                "INSERT INTO Seats (hall_id, row_number, seat_number, seat_type)
                VALUES (?, ?, ?, 'Regular')"
            );
            for ($r = 1; $r <= $rows; $r++) {
                for ($s = 1; $s <= $seatsPerRow; $s++) {
                    $insertSeat->execute([$hallId, $r, $s]);
                }
            }
            $this->db->commit();

            return json_encode(['success' => true, 'hall_id' => $hallId]);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

?>