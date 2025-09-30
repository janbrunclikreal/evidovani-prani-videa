<?php
// Evidence přání videií v3.1.1
// Audit Log System - Kompletní sledování všech akcí uživatelů

require_once 'database.php';

class AuditLog {
    private $db;
    
    // Typy akcí pro kategorizaci
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_CREATE_RECORD = 'create_record';
    const ACTION_UPDATE_RECORD = 'update_record';
    const ACTION_DELETE_RECORD = 'delete_record';
    const ACTION_CREATE_USER = 'create_user';
    const ACTION_UPDATE_USER = 'update_user';
    const ACTION_DELETE_USER = 'delete_user';
    const ACTION_CHANGE_PASSWORD = 'change_password';
    const ACTION_EXPORT_DATA = 'export_data';
    const ACTION_IMPORT_DATA = 'import_data';
    const ACTION_VIEW_RECORDS = 'view_records';
    const ACTION_VIEW_REPORTS = 'view_reports';
    const ACTION_FAILED_LOGIN = 'failed_login';
    
    // Severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Záznam nové audit log položky
     */
    public function log($actionType, $userId = null, $username = null, $details = [], $affectedTable = null, $affectedId = null, $oldValues = null, $newValues = null, $severity = self::SEVERITY_INFO) {
        try {
            $sql = "INSERT INTO audit_log (
                user_id, username, action_type, affected_table, affected_id,
                old_values, new_values, details, ip_address, user_agent,
                severity, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $userId,
                $username,
                $actionType,
                $affectedTable,
                $affectedId,
                $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                json_encode($details, JSON_UNESCAPED_UNICODE),
                $this->getClientIP(),
                $this->getUserAgent(),
                $severity
            ];
            
            $this->db->query($sql, $params);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            // Fallback logging do souboru pokud databáze selže
            $this->logToFile($actionType, $userId, $username, $details, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Získání audit logů s filtry a stránkováním
     */
    public function getLogs($page = 1, $limit = 50, $filters = []) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];
        
        // Aplikace filtrů
        if (!empty($filters['user_id'])) {
            $whereConditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['username'])) {
            $whereConditions[] = "username LIKE ?";
            $params[] = '%' . $filters['username'] . '%';
        }
        
        if (!empty($filters['action_type'])) {
            $whereConditions[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['severity'])) {
            $whereConditions[] = "severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['ip_address'])) {
            $whereConditions[] = "ip_address = ?";
            $params[] = $filters['ip_address'];
        }
        
        // Sestavení WHERE klauzule
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Dotaz na data
        $sql = "SELECT 
                    id, user_id, username, action_type, affected_table, affected_id,
                    old_values, new_values, details, ip_address, user_agent,
                    severity, created_at
                FROM audit_log 
                $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $logs = $this->db->fetchAll($sql, $params);
        
        // Dotaz na celkový počet
        $countSql = "SELECT COUNT(*) as total FROM audit_log $whereClause";
        $countParams = array_slice($params, 0, -2); // Odebere LIMIT a OFFSET
        $totalResult = $this->db->fetch($countSql, $countParams);
        $total = $totalResult['total'];
        
        return [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit
            ]
        ];
    }
    
    /**
     * Získání statistik audit logů
     */
    public function getStatistics($dateFrom = null, $dateTo = null) {
        $whereClause = '';
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $whereClause = 'WHERE DATE(created_at) BETWEEN ? AND ?';
            $params = [$dateFrom, $dateTo];
        } elseif ($dateFrom) {
            $whereClause = 'WHERE DATE(created_at) >= ?';
            $params = [$dateFrom];
        } elseif ($dateTo) {
            $whereClause = 'WHERE DATE(created_at) <= ?';
            $params = [$dateTo];
        }
        
        // Celkový počet akcí
        $totalSql = "SELECT COUNT(*) as total FROM audit_log $whereClause";
        $total = $this->db->fetch($totalSql, $params)['total'];
        
        // Akce podle typu
        $actionsSql = "SELECT action_type, COUNT(*) as count 
                      FROM audit_log $whereClause 
                      GROUP BY action_type 
                      ORDER BY count DESC";
        $actionStats = $this->db->fetchAll($actionsSql, $params);
        
        // Akce podle uživatelů
        $usersSql = "SELECT username, COUNT(*) as count 
                    FROM audit_log $whereClause 
                    GROUP BY username 
                    ORDER BY count DESC 
                    LIMIT 10";
        $userStats = $this->db->fetchAll($usersSql, $params);
        
        // Akce podle severity
        $severitySql = "SELECT severity, COUNT(*) as count 
                       FROM audit_log $whereClause 
                       GROUP BY severity";
        $severityStats = $this->db->fetchAll($severitySql, $params);
        
        // Denní aktivita
        $dailySql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                    FROM audit_log $whereClause 
                    GROUP BY DATE(created_at) 
                    ORDER BY date DESC 
                    LIMIT 30";
        $dailyStats = $this->db->fetchAll($dailySql, $params);
        
        return [
            'total_actions' => $total,
            'actions_by_type' => $actionStats,
            'actions_by_user' => $userStats,
            'actions_by_severity' => $severityStats,
            'daily_activity' => $dailyStats
        ];
    }
    
    /**
     * Smazání starých audit logů (data retention)
     */
    public function cleanupOldLogs($daysToKeep = 365) {
        $sql = "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $deleted = $this->db->query($sql, [$daysToKeep]);
        
        $this->log(
            'cleanup_audit_logs',
            null,
            'system',
            ['days_to_keep' => $daysToKeep, 'deleted_records' => $deleted],
            'audit_log',
            null,
            null,
            null,
            self::SEVERITY_INFO
        );
        
        return $deleted;
    }
    
    /**
     * Export audit logů do CSV
     */
    public function exportToCsv($filters = []) {
        $result = $this->getLogs(1, 10000, $filters); // Export až 10000 záznamů
        $logs = $result['logs'];
        
        $csv = "ID,Uživatel ID,Uživatelské jméno,Typ akce,Tabulka,ID záznamu,Původní hodnoty,Nové hodnoty,Detaily,IP adresa,Prohlížeč,Závažnost,Datum a čas\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $log['id'],
                $log['user_id'] ?? '',
                $log['username'] ?? '',
                $log['action_type'],
                $log['affected_table'] ?? '',
                $log['affected_id'] ?? '',
                str_replace('"', '""', $log['old_values'] ?? ''),
                str_replace('"', '""', $log['new_values'] ?? ''),
                str_replace('"', '""', $log['details'] ?? ''),
                $log['ip_address'],
                str_replace('"', '""', $log['user_agent']),
                $log['severity'],
                $log['created_at']
            );
        }
        
        return $csv;
    }
    
    /**
     * Získání IP adresy klienta
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Získání user agent
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Fallback logging do souboru
     */
    private function logToFile($actionType, $userId, $username, $details, $error) {
        $logDir = 'logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/audit_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] ACTION:%s USER:%s(%s) DETAILS:%s ERROR:%s IP:%s\n",
            $timestamp,
            $actionType,
            $username ?? 'unknown',
            $userId ?? 'unknown',
            json_encode($details),
            $error,
            $this->getClientIP()
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Pomocné metody pro snadné logování specifických akcí
     */
    
    public function logLogin($userId, $username, $success = true) {
        $actionType = $success ? self::ACTION_LOGIN : self::ACTION_FAILED_LOGIN;
        $severity = $success ? self::SEVERITY_INFO : self::SEVERITY_WARNING;
        
        return $this->log(
            $actionType,
            $userId,
            $username,
            ['login_success' => $success],
            'users',
            $userId,
            null,
            null,
            $severity
        );
    }
    
    public function logLogout($userId, $username) {
        return $this->log(
            self::ACTION_LOGOUT,
            $userId,
            $username,
            [],
            'users',
            $userId,
            null,
            null,
            self::SEVERITY_INFO
        );
    }
    
    public function logRecordCreate($userId, $username, $recordId, $recordData) {
        return $this->log(
            self::ACTION_CREATE_RECORD,
            $userId,
            $username,
            ['record_created' => true],
            'records',
            $recordId,
            null,
            $recordData,
            self::SEVERITY_INFO
        );
    }
    
    public function logRecordUpdate($userId, $username, $recordId, $oldData, $newData) {
        return $this->log(
            self::ACTION_UPDATE_RECORD,
            $userId,
            $username,
            ['record_updated' => true],
            'records',
            $recordId,
            $oldData,
            $newData,
            self::SEVERITY_INFO
        );
    }
    
    public function logRecordDelete($userId, $username, $recordId, $recordData) {
        return $this->log(
            self::ACTION_DELETE_RECORD,
            $userId,
            $username,
            ['record_deleted' => true],
            'records',
            $recordId,
            $recordData,
            null,
            self::SEVERITY_WARNING
        );
    }
    
    public function logUserCreate($userId, $username, $newUserId, $newUserData) {
        return $this->log(
            self::ACTION_CREATE_USER,
            $userId,
            $username,
            ['user_created' => true],
            'users',
            $newUserId,
            null,
            $newUserData,
            self::SEVERITY_INFO
        );
    }
    
    public function logUserUpdate($userId, $username, $targetUserId, $oldData, $newData) {
        return $this->log(
            self::ACTION_UPDATE_USER,
            $userId,
            $username,
            ['user_updated' => true],
            'users',
            $targetUserId,
            $oldData,
            $newData,
            self::SEVERITY_INFO
        );
    }
    
    public function logUserDelete($userId, $username, $deletedUserId, $userData) {
        return $this->log(
            self::ACTION_DELETE_USER,
            $userId,
            $username,
            ['user_deleted' => true],
            'users',
            $deletedUserId,
            $userData,
            null,
            self::SEVERITY_CRITICAL
        );
    }
    
    public function logPasswordChange($userId, $username, $targetUserId) {
        return $this->log(
            self::ACTION_CHANGE_PASSWORD,
            $userId,
            $username,
            ['password_changed' => true],
            'users',
            $targetUserId,
            null,
            null,
            self::SEVERITY_WARNING
        );
    }
    
    public function logDataExport($userId, $username, $exportType, $recordCount) {
        return $this->log(
            self::ACTION_EXPORT_DATA,
            $userId,
            $username,
            ['export_type' => $exportType, 'record_count' => $recordCount],
            null,
            null,
            null,
            null,
            self::SEVERITY_INFO
        );
    }
    
    public function logDataImport($userId, $username, $importType, $recordCount, $success = true) {
        $severity = $success ? self::SEVERITY_INFO : self::SEVERITY_ERROR;
        
        return $this->log(
            self::ACTION_IMPORT_DATA,
            $userId,
            $username,
            ['import_type' => $importType, 'record_count' => $recordCount, 'success' => $success],
            null,
            null,
            null,
            null,
            $severity
        );
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Celkový počet akcí
        $sql = "SELECT COUNT(*) as total_actions FROM audit_log";
        $result = $this->db->fetch($sql);
        $stats['total_actions'] = $result['total_actions'];
        
        // Akce za posledních 24 hodin
        $sql = "SELECT COUNT(*) as recent_actions FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->db->fetch($sql);
        $stats['recent_actions'] = $result['recent_actions'];
        
        // Top 5 typů akcí
        $sql = "SELECT action_type, COUNT(*) as count FROM audit_log GROUP BY action_type ORDER BY count DESC LIMIT 5";
        $result = $this->db->fetchAll($sql);
        $stats['top_actions'] = $result;
        
        // Distribuce podle závažnosti
        $sql = "SELECT severity, COUNT(*) as count FROM audit_log GROUP BY severity";
        $result = $this->db->fetchAll($sql);
        $stats['by_severity'] = $result;
        
        // Aktivita za posledních 7 dní
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM audit_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date";
        $result = $this->db->fetchAll($sql);
        $stats['daily_activity'] = $result;
        
        return $stats;
    }
}
?>