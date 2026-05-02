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
                case 'getReport1':
                    return $this->getReport1();
                case 'getReport2':
                    return $this->getReport2();
                case 'getReport3':
                    return $this->getReport3();
                case 'getActiveMovies':
                    return $this->getActiveMovies();
                case 'getPopularMovies':
                    return $this->getPopularMovies();
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

    private function getReport1() {
        $from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['date_to'] ?? date('Y-m-d');
        $sort = $_GET['sort'] ?? 'total_tickets';
        $order = $_GET['order'] ?? 'DESC';
        $allowedSort = ['hall_number', 'total_tickets', 'revenue'];

        if (!in_array($sort, $allowedSort))
            $sort = 'total_tickets';

        $sql = "SELECT
                h.hall_number,
                COUNT(t.ticket_id) as total_tickets,
                COALESCE(SUM(t.price), 0) as revenue
            FROM Halls h
            LEFT JOIN Sessions s ON h.hall_id = s.hall_id
            LEFT JOIN Tickets t ON s.session_id = t.session_id AND t.status = 'sold'
            WHERE date(t.purchase_date) BETWEEN :from AND :to OR t.ticket_id IS NULL
            GROUP BY h.hall_id
            ORDER BY $sort $order";

        $query = $this->db->prepare($sql);
        $query->execute([':from' => $from, ':to' => $to]);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalTickets = array_sum(array_column($data, 'total_tickets'));
        $totalRevenue = array_sum(array_column($data, 'revenue'));

        return json_encode([
            'success' => true,
            'data' => $data,
            'totals' => [
                'tickets' => $totalTickets,
                'revenue' => $totalRevenue
            ]
        ]);
    }

    private function getReport2() {
        $minTickets = (int)($_GET['min_tickets'] ?? 0);
        $sort = $_GET['sort'] ?? 'tickets_sold';
        $order = $_GET['order'] ?? 'DESC';
        $allowedSort = ['title', 'tickets_sold', 'revenue'];

        if (!in_array($sort, $allowedSort))
            $sort = 'tickets_sold';

        $sql = "SELECT
                m.title,
                COUNT(t.ticket_id) as tickets_sold,
                COALESCE(SUM(t.price), 0) as revenue
            FROM Movies m
            LEFT JOIN Sessions s ON m.movie_id = s.movie_id
            LEFT JOIN Tickets t ON s.session_id = t.session_id AND t.status = 'sold'
        ";

        $params = [];
        if (!empty($_GET['genre']))
        {
            $sql .= " WHERE m.genre = :genre";
            $params = [':genre' => $_GET['genre']];
        }

        $sql .= " GROUP BY m.movie_id
            HAVING COUNT(t.ticket_id) >= " . $minTickets . "
            ORDER BY $sort $order
        ";

        $query = $this->db->prepare($sql);
        $query->execute($params);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalTickets = array_sum(array_column($data, 'tickets_sold'));
        $totalRevenue = array_sum(array_column($data, 'revenue'));

        return json_encode([
            'success' => true,
            'data' => $data,
            'totals' => [
                'tickets' => $totalTickets,
                'revenue' => $totalRevenue
            ]
        ]);
    }

    private function getReport3() {
        $from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['date_to'] ?? date('Y-m-d');
        $sort = $_GET['sort'] ?? 'purchase_date';
        $order = $_GET['order'] ?? 'ASC';
        $allowedSort = ['purchase_date', 'daily_revenue', 'tickets_count'];

        if (!in_array($sort, $allowedSort))
            $sort = 'purchase_date';

        $sql = "SELECT
                date(t.purchase_date) as purchase_date,
                COUNT(t.ticket_id) as tickets_count,
                COALESCE(SUM(t.price), 0) as daily_revenue
            FROM Tickets t
            WHERE t.status = 'sold' AND date(t.purchase_date) BETWEEN :from AND :to
            GROUP BY date(t.purchase_date)
            ORDER BY $sort $order";
        $query = $this->db->prepare($sql);
        $query->execute([':from' => $from, ':to' => $to]);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalTickets = array_sum(array_column($data, 'tickets_count'));
        $totalRevenue = array_sum(array_column($data, 'daily_revenue'));

        return json_encode([
            'success' => true,
            'data' => $data,
            'totals' => [
                'tickets' => $totalTickets,
                'revenue' => $totalRevenue
            ]
        ]);
    }

    private function getActiveMovies() {
        $sql = "SELECT * FROM ActiveMovies";
        $query = $this->db->query($sql);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function getPopularMovies() {
        $sql = "SELECT * FROM PopularMovies";
        $query = $this->db->query($sql);
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        return json_encode(['success' => true, 'data' => $data]);
    }
}

?>