<?php
// Evidence přání videií v3.1.1
// Model pro záznamy

require_once 'database.php';

class Record {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function createRecord($data, $userId) {
        $sql = "INSERT INTO records (datum, jmeno, ucet, castka, stav, prani, nick, link, faktura, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Bezpečné získání hodnot s výchozími hodnotami
        $params = [
            $data['datum'] ?? null,
            $data['jmeno'] ?? null,
            $data['ucet'] ?? null,
            !empty($data['castka']) ? $data['castka'] : null,
            $data['stav'] ?? 'rozpracovane',
            $data['prani'] ?? null,
            $data['nick'] ?? null,
            $data['link'] ?? null,
            $data['faktura'] ?? null,
            $userId
        ];
        
        $this->db->query($sql, $params);
        
        return $this->db->lastInsertId();
    }
    
    public function getRecords($page = 1, $filters = []) {
        $offset = ($page - 1) * RECORDS_PER_PAGE;
        $whereConditions = [];
        $params = [];
        
        // Filtrování
        if (!empty($filters['id'])) {
            $whereConditions[] = "id = ?";
            $params[] = intval($filters['id']);
        }
        
        if (!empty($filters['stav'])) {
            $whereConditions[] = "stav = ?";
            $params[] = $filters['stav'];
        }
        
        if (!empty($filters['prani'])) {
            $whereConditions[] = "prani LIKE ?";
            $params[] = '%' . $filters['prani'] . '%';
        }
        
        if (!empty($filters['nick'])) {
            $whereConditions[] = "nick LIKE ?";
            $params[] = '%' . $filters['nick'] . '%';
        }
        
        if (!empty($filters['datum_od'])) {
            $whereConditions[] = "datum >= ?";
            $params[] = $filters['datum_od'];
        }
        
        if (!empty($filters['datum_do'])) {
            $whereConditions[] = "datum <= ?";
            $params[] = $filters['datum_do'];
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        // Celkový počet záznamů
        $countSql = "SELECT COUNT(*) as total FROM records $whereClause";
        $totalRecords = $this->db->fetch($countSql, $params);
        
        // Záznamy pro aktuální stránku
        $recordsSql = "SELECT id, datum, jmeno, ucet, castka, stav, prani, nick, link, faktura, created_at, updated_at 
                       FROM records 
                       $whereClause 
                       ORDER BY datum DESC, id DESC 
                       LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset";
        
        $records = $this->db->fetchAll($recordsSql, $params);
        
        return [
            'records' => $records,
            'total' => $totalRecords['total'],
            'current_page' => $page,
            'per_page' => RECORDS_PER_PAGE,
            'pages' => ceil($totalRecords['total'] / RECORDS_PER_PAGE)
        ];
    }
    
    public function getRecordsByDate($date) {
        $sql = "SELECT id, datum, jmeno, ucet, castka, stav, prani, nick, link, faktura, created_at, updated_at 
                FROM records 
                WHERE DATE(datum) = ?
                ORDER BY id DESC";
        
        return $this->db->fetchAll($sql, [$date]);
    }
    
    public function updateRecord($id, $data) {
        $sql = "UPDATE records SET datum = ?, jmeno = ?, ucet = ?, castka = ?, stav = ?, 
                prani = ?, nick = ?, link = ?, faktura = ?, updated_at = NOW() WHERE id = ?";
        
        // Bezpečné získání hodnot s výchozými hodnotami
        $params = [
            $data['datum'] ?? null,
            $data['jmeno'] ?? null,
            $data['ucet'] ?? null,
            !empty($data['castka']) ? $data['castka'] : null,
            $data['stav'] ?? 'rozpracovane',
            $data['prani'] ?? null,
            $data['nick'] ?? null,
            $data['link'] ?? null,
            $data['faktura'] ?? null,
            $id
        ];
        
        $this->db->query($sql, $params);
        
        return true;
    }
    
    public function getRecordById($id) {
        $sql = "SELECT * FROM records WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    
    public function deleteRecord($id) {
        $this->db->query("DELETE FROM records WHERE id = ?", [$id]);
        return true;
    }
    
    public function getMonthlyOverview($year, $month) {
        // Přehled podle stavů
        $statusSql = "SELECT 
                    stav,
                    COUNT(*) as pocet_zaznamu,
                    SUM(IFNULL(castka, 0)) as celkova_castka
                FROM records 
                WHERE YEAR(datum) = ? AND MONTH(datum) = ?
                GROUP BY stav
                ORDER BY stav";
        
        return $this->db->fetchAll($statusSql, [$year, $month]);
    }
    
    public function getMonthlyDetailOverview($year, $month) {
        // Detail podle dnů
        $dailySql = "SELECT 
                    DATE(datum) as datum,
                    COUNT(*) as pocet_zaznamu,
                    SUM(IFNULL(castka, 0)) as celkova_castka,
                    SUM(CASE WHEN stav = 'zaplaceno' THEN IFNULL(castka, 0) ELSE 0 END) as zaplaceno_castka
                FROM records 
                WHERE YEAR(datum) = ? AND MONTH(datum) = ?
                GROUP BY DATE(datum)
                ORDER BY DATE(datum)";
        
        return $this->db->fetchAll($dailySql, [$year, $month]);
    }
    
    public function getYearlyOverview($year) {
        $sql = "SELECT 
                    MONTH(datum) as mesic,
                    COUNT(*) as pocet_zaznamu,
                    SUM(IFNULL(castka, 0)) as celkova_castka,
                    SUM(CASE WHEN stav = 'zaplaceno' THEN IFNULL(castka, 0) ELSE 0 END) as zaplaceno_castka
                FROM records 
                WHERE YEAR(datum) = ?
                GROUP BY MONTH(datum)
                ORDER BY MONTH(datum)";
        
        return $this->db->fetchAll($sql, [$year]);
    }
    
    public function exportToCSV($filters = []) {
        $whereConditions = [];
        $params = [];
        
        // Aplikace filtrů
        if (!empty($filters['datum_od'])) {
            $whereConditions[] = "datum >= ?";
            $params[] = $filters['datum_od'];
        }
        
        if (!empty($filters['datum_do'])) {
            $whereConditions[] = "datum <= ?";
            $params[] = $filters['datum_do'];
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        $sql = "SELECT datum, jmeno, ucet, castka, stav, prani, nick, link, faktura 
                FROM records $whereClause ORDER BY datum DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Celkový počet záznamů
        $sql = "SELECT COUNT(*) as total_records FROM records";
        $result = $this->db->fetch($sql);
        $stats['total_records'] = $result['total_records'];
        
        // Počet záznamů podle stavů
        $sql = "SELECT stav, COUNT(*) as count FROM records GROUP BY stav";
        $result = $this->db->fetchAll($sql);
        $stats['by_status'] = $result;
        
        // Záznamy za posledních 7 dní
        $sql = "SELECT COUNT(*) as recent_records FROM records WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $result = $this->db->fetch($sql);
        $stats['recent_records'] = $result['recent_records'];
        
        // Nejaktivnější uživatelé (top 5)
        $sql = "SELECT u.username, COUNT(*) as count 
                FROM records r 
                LEFT JOIN users u ON r.created_by = u.id 
                WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY r.created_by, u.username 
                ORDER BY count DESC 
                LIMIT 5";
        $result = $this->db->fetchAll($sql);
        $stats['top_users'] = $result;
        
        return $stats;
    }
    
    public function getTrends($days = 30) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    stav
                FROM records 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), stav
                ORDER BY date DESC";
        
        return $this->db->fetchAll($sql, [$days]);
    }
    
    public function getStatusDistribution() {
        $sql = "SELECT 
                    stav as status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM records), 2) as percentage
                FROM records 
                GROUP BY stav
                ORDER BY count DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getFinancialStats() {
        $stats = [];
        
        // Celková částka
        $sql = "SELECT 
                    SUM(CASE WHEN castka IS NOT NULL THEN castka ELSE 0 END) as total_amount,
                    AVG(CASE WHEN castka IS NOT NULL THEN castka ELSE 0 END) as avg_amount,
                    COUNT(CASE WHEN castka IS NOT NULL AND castka > 0 THEN 1 END) as records_with_amount
                FROM records";
        $result = $this->db->fetch($sql);
        $stats['totals'] = $result;
        
        // Částky podle stavů
        $sql = "SELECT 
                    stav,
                    SUM(CASE WHEN castka IS NOT NULL THEN castka ELSE 0 END) as total_amount,
                    COUNT(*) as count
                FROM records 
                GROUP BY stav";
        $result = $this->db->fetchAll($sql);
        $stats['by_status'] = $result;
        
        // Trendy za posledních 6 měsíců
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(CASE WHEN castka IS NOT NULL THEN castka ELSE 0 END) as total_amount,
                    COUNT(*) as count
                FROM records 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month";
        $result = $this->db->fetchAll($sql);
        $stats['monthly_trends'] = $result;
        
        return $stats;
    }
}
?>