<?php
// Evidence přání videií v3.1.1
// Hlavní API endpoint

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Error handling pro development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Skryjeme errors před uživatelem, ale logujeme je
ini_set('log_errors', 1);

// Ensure JSON output even with errors
ob_start();

require_once 'config.php';
require_once 'User.php';
require_once 'Record.php';
require_once 'AuditLog.php';

class API {
    private $user;
    private $record;
    private $auditLog;
    
    public function __construct() {
        try {
            $this->user = new User();
            $this->record = new Record();
            $this->auditLog = new AuditLog();
        } catch (Exception $e) {
            $this->error('Chyba inicializace: ' . $e->getMessage(), 500);
        }
    }
    
    public function handleRequest() {
        try {
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'login':
                    return $this->login();
                case 'logout':
                    return $this->logout();
                case 'check-session':
                    return $this->checkSession();
                    
                // Záznamy
                case 'records':
                case 'get-records':
                    return $this->getRecords();
                case 'create-record':
                    return $this->createRecord();
                case 'update-record':
                    return $this->updateRecord();
                case 'delete-record':
                    return $this->deleteRecord();
                    
                // Přehledy
                case 'monthly-overview':
                    return $this->getMonthlyOverview();
                case 'monthly-detail':
                    return $this->getMonthlyDetailOverview();
                case 'yearly-overview':
                    return $this->getYearlyOverview();
                    
                // Export/Import
                case 'export-csv':
                    return $this->exportCSV();
                case 'import-csv':
                    return $this->importCSV();
                    
                // Uživatelé
                case 'get-users':
                    return $this->getUsers();
                case 'create-user':
                    return $this->createUser();
                case 'update-user':
                    return $this->updateUser();
                case 'delete-user':
                    return $this->deleteUser();
                case 'change-password':
                    return $this->changePassword();
                case 'debug-create':
                    return $this->debugCreate();
                    
                // Audit Log
                case 'get-audit-logs':
                    return $this->getAuditLogs();
                case 'audit-statistics':
                    return $this->getAuditStatistics();
                case 'export-audit-csv':
                    return $this->exportAuditCSV();
                case 'cleanup-audit-logs':
                    return $this->cleanupAuditLogs();
                    
                case 'dashboard-stats':
                    return $this->getDashboardStats();
                    
                default:
                    return $this->error('Neplatná akce', 400);
            }
        } catch (Exception $e) {
            error_log('API chyba: ' . $e->getMessage());
            return $this->error('Interní chyba serveru: ' . $e->getMessage(), 500);
        }
    }
    
    private function login() {
        $data = $this->getRequestData();
        
        if (empty($data['username']) || empty($data['password'])) {
            return $this->error('Chybí uživatelské jméno nebo heslo');
        }
        
        $user = $this->user->authenticate($data['username'], $data['password']);
        
        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['login_time'] = time();
            
            // Audit log pro úspěšné přihlášení
            $this->auditLog->logLogin($user['id'], $user['username'], true);
            
            return $this->success('Přihlášení úspěšné', $user);
        }
        
        // Audit log pro neúspěšné přihlášení
        $this->auditLog->logLogin(null, $data['username'], false);
        
        return $this->error('Neplatné přihlašovací údaje');
    }
    
    private function logout() {
        $user = $_SESSION['user'] ?? null;
        
        if ($user) {
            // Audit log pro odhlášení
            $this->auditLog->logLogout($user['id'], $user['username']);
        }
        
        session_destroy();
        return $this->success('Odhlášení úspěšné');
    }
    
    private function checkSession() {
        if ($this->isLoggedIn()) {
            return $this->success('Přihlášen', $_SESSION['user']);
        }
        return $this->error('Nepřihlášen', 401);
    }
    
    private function createRecord() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        try {
            $data = $this->getRequestData();
            
            // Validace povinných polí
            if (empty($data['datum']) || empty($data['jmeno'])) {
                return $this->error('Chybí povinná pole');
            }
            
            $recordId = $this->record->createRecord($data, $_SESSION['user']['id']);
            
            // Audit log pro vytvoření záznamu
            $this->auditLog->logRecordCreate(
                $_SESSION['user']['id'], 
                $_SESSION['user']['username'], 
                $recordId, 
                $data
            );
            
            return $this->success('Záznam vytvořen', ['id' => $recordId]);
        } catch (Exception $e) {
            error_log('Chyba při vytváření záznamu: ' . $e->getMessage());
            return $this->error('Chyba při vytváření záznamu: ' . $e->getMessage());
        }
    }
    
    private function getRecords() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pro kompatibilitu s testovacími soubory - pokud je zadán parametr date
        if (isset($_GET['date'])) {
            $date = $_GET['date'];
            $records = $this->record->getRecordsByDate($date);
            return $this->success('Záznamy načteny', $records);
        }
        
        $page = intval($_GET['page'] ?? 1);
        $filters = [
            'id' => $_GET['id'] ?? '',
            'stav' => $_GET['stav'] ?? '',
            'prani' => $_GET['prani'] ?? '',
            'nick' => $_GET['nick'] ?? '',
            'datum_od' => $_GET['datum_od'] ?? '',
            'datum_do' => $_GET['datum_do'] ?? ''
        ];
        
        $result = $this->record->getRecords($page, $filters);
        return $this->success('Záznamy načteny', $result);
    }
    
    private function updateRecord() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může editovat záznamy
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může editovat záznamy', 403);
        }
        
        try {
            $id = intval($_GET['id'] ?? 0);
            $data = $this->getRequestData();
            
            if ($id <= 0) {
                return $this->error('Neplatné ID záznamu');
            }
            
            // Získání původních dat pro audit log
            $oldData = $this->record->getRecordById($id);
            if (!$oldData) {
                return $this->error('Záznam nenalezen');
            }
            
            $this->record->updateRecord($id, $data);
            
            // Audit log pro aktualizaci záznamu
            $this->auditLog->logRecordUpdate(
                $_SESSION['user']['id'], 
                $_SESSION['user']['username'], 
                $id, 
                $oldData, 
                $data
            );
            
            return $this->success('Záznam aktualizován');
        } catch (Exception $e) {
            error_log('Chyba při aktualizaci záznamu: ' . $e->getMessage());
            return $this->error('Chyba při aktualizaci záznamu: ' . $e->getMessage());
        }
    }
    
    private function deleteRecord() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může mazat záznamy
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může mazat záznamy', 403);
        }
        
        try {
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                return $this->error('Neplatné ID záznamu');
            }
            
            // Získání dat před smazáním pro audit log
            $recordData = $this->record->getRecordById($id);
            if (!$recordData) {
                return $this->error('Záznam nenalezen');
            }
            
            $this->record->deleteRecord($id);
            
            // Audit log pro smazání záznamu
            $this->auditLog->logRecordDelete(
                $_SESSION['user']['id'], 
                $_SESSION['user']['username'], 
                $id, 
                $recordData
            );
            
            return $this->success('Záznam smazán');
        } catch (Exception $e) {
            error_log('Chyba při mazání záznamu: ' . $e->getMessage());
            return $this->error('Chyba při mazání záznamu: ' . $e->getMessage());
        }
    }
    
    private function getMonthlyOverview() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));
        
        $result = $this->record->getMonthlyOverview($year, $month);
        return $this->success('Měsíční přehled načten', $result);
    }
    
    private function getMonthlyDetailOverview() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));
        
        $result = $this->record->getMonthlyDetailOverview($year, $month);
        return $this->success('Měsíční detail načten', $result);
    }
    
    private function getYearlyOverview() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        $year = intval($_GET['year'] ?? date('Y'));
        
        $result = $this->record->getYearlyOverview($year);
        return $this->success('Roční přehled načten', $result);
    }
    
    private function exportCSV() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        $filters = [
            'datum_od' => $_GET['datum_od'] ?? '',
            'datum_do' => $_GET['datum_do'] ?? ''
        ];
        
        $records = $this->record->exportToCSV($filters);
        
        // Generování CSV
        $filename = 'evidence_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM pro správné zobrazení v Excelu
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Hlavička CSV
        fputcsv($output, ['Datum', 'Jméno', 'Účet', 'Částka', 'Stav', 'Přání', 'Nick', 'Link', 'Faktura'], ';');
        
        // Data
        foreach ($records as $record) {
            fputcsv($output, [
                $record['datum'],
                $record['jmeno'],
                $record['ucet'],
                $record['castka'],
                $record['stav'],
                $record['prani'],
                $record['nick'],
                $record['link'],
                $record['faktura']
            ], ';');
        }
        
        fclose($output);
        exit;
    }
    
    private function importCSV() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        if (!isset($_FILES['csv_file'])) {
            return $this->error('Chybí CSV soubor');
        }
        
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error('Chyba při nahrávání souboru');
        }
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return $this->error('Nelze otevřít CSV soubor');
        }
        
        $imported = 0;
        $errors = [];
        
        // Přeskočení hlavičky
        fgetcsv($handle, 1000, ';');
        
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            try {
                if (count($data) >= 2) { // Minimálně datum a jméno
                    $recordData = [
                        'datum' => $data[0] ?? '',
                        'jmeno' => $data[1] ?? '',
                        'ucet' => $data[2] ?? '',
                        'castka' => $data[3] ?? 0,
                        'stav' => $data[4] ?? 'rozpracovane',
                        'prani' => $data[5] ?? '',
                        'nick' => $data[6] ?? '',
                        'link' => $data[7] ?? '',
                        'faktura' => $data[8] ?? ''
                    ];
                    
                    $this->record->createRecord($recordData, $_SESSION['user']['id']);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors[] = "Řádek " . ($imported + 1) . ": " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        return $this->success("Importováno $imported záznamů", ['imported' => $imported, 'errors' => $errors]);
    }
    
    private function getUsers() {
        if (!$this->isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění', 403);
        }
        
        $users = $this->user->getAllUsers();
        return $this->success('Uživatelé načteni', $users);
    }
    
    private function createUser() {
        if (!$this->isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění', 403);
        }
        
        $data = $this->getRequestData();
        
        if (empty($data['username']) || empty($data['password'])) {
            return $this->error('Chybí uživatelské jméno nebo heslo');
        }
        
        $userId = $this->user->createUser($data['username'], $data['password'], $data['role'] ?? 'user');
        return $this->success('Uživatel vytvořen', ['id' => $userId]);
    }
    
    private function updateUser() {
        if (!$this->isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění', 403);
        }
        
        $id = intval($_GET['id'] ?? 0);
        $data = $this->getRequestData();
        
        if ($id <= 0) {
            return $this->error('Neplatné ID uživatele');
        }
        
        $this->user->updateUser($id, $data['username'], $data['role']);
        return $this->success('Uživatel aktualizován');
    }
    
    private function deleteUser() {
        if (!$this->isLoggedIn() || $_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění', 403);
        }
        
        $id = intval($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            return $this->error('Neplatné ID uživatele');
        }
        
        $this->user->deleteUser($id);
        return $this->success('Uživatel smazán');
    }
    
    private function changePassword() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        $data = $this->getRequestData();
        $userId = $_SESSION['user']['id'];
        
        // Admin může měnit heslo jiným uživatelům
        if ($_SESSION['user']['role'] === 'admin' && isset($data['user_id'])) {
            $userId = intval($data['user_id']);
        }
        
        if (empty($data['new_password'])) {
            return $this->error('Chybí nové heslo');
        }
        
        $this->user->changePassword($userId, $data['new_password']);
        return $this->success('Heslo změněno');
    }
    
    private function debugCreate() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        try {
            $data = $this->getRequestData();
            
            // Vytvoř debug informace
            $debugInfo = [
                'received_data' => $data,
                'session_user' => $_SESSION['user'] ?? null,
                'post_data' => $_POST,
                'get_data' => $_GET,
                'input' => file_get_contents('php://input'),
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ];
            
            return $this->success('Debug informace', $debugInfo);
        } catch (Exception $e) {
            return $this->error('Debug chyba: ' . $e->getMessage());
        }
    }
    
    private function isLoggedIn() {
        if (!isset($_SESSION['user']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Kontrola timeoutu
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
        
        return true;
    }
    
    private function getRequestData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null) {
            return $_POST;
        }
        
        return $data;
    }
    
    private function success($message, $data = null) {
        // Clear any potential output buffers that might contain HTML errors
        if (ob_get_length()) {
            ob_clean();
        }
        
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function error($message, $code = 400) {
        // Clear any potential output buffers that might contain HTML errors
        if (ob_get_length()) {
            ob_clean();
        }
        
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Audit Log metody
    
    private function getAuditLogs() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může přistupovat k audit logům
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může zobrazit audit logy', 403);
        }
        
        try {
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            
            // Filtry
            $filters = [];
            if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
            if (!empty($_GET['username'])) $filters['username'] = $_GET['username'];
            if (!empty($_GET['action_type'])) $filters['action_type'] = $_GET['action_type'];
            if (!empty($_GET['severity'])) $filters['severity'] = $_GET['severity'];
            if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
            if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
            if (!empty($_GET['ip_address'])) $filters['ip_address'] = $_GET['ip_address'];
            
            $result = $this->auditLog->getLogs($page, $limit, $filters);
            
            // Audit log pro prohlížení audit logů
            $this->auditLog->log(
                AuditLog::ACTION_VIEW_RECORDS,
                $_SESSION['user']['id'],
                $_SESSION['user']['username'],
                ['viewed_audit_logs' => true, 'page' => $page, 'filters' => $filters],
                'audit_log',
                null,
                null,
                null,
                AuditLog::SEVERITY_INFO
            );
            
            return $this->success('Audit logy načteny', $result);
        } catch (Exception $e) {
            error_log('Chyba při načítání audit logů: ' . $e->getMessage());
            return $this->error('Chyba při načítání audit logů: ' . $e->getMessage());
        }
    }
    
    private function getAuditStatistics() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může přistupovat k audit statistikám
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může zobrazit audit statistiky', 403);
        }
        
        try {
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            $statistics = $this->auditLog->getStatistics($dateFrom, $dateTo);
            
            // Audit log pro prohlížení statistik
            $this->auditLog->log(
                AuditLog::ACTION_VIEW_REPORTS,
                $_SESSION['user']['id'],
                $_SESSION['user']['username'],
                ['viewed_audit_statistics' => true, 'date_from' => $dateFrom, 'date_to' => $dateTo],
                'audit_log',
                null,
                null,
                null,
                AuditLog::SEVERITY_INFO
            );
            
            return $this->success('Audit statistiky načteny', $statistics);
        } catch (Exception $e) {
            error_log('Chyba při načítání audit statistik: ' . $e->getMessage());
            return $this->error('Chyba při načítání audit statistik: ' . $e->getMessage());
        }
    }
    
    private function exportAuditCSV() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může exportovat audit logy
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může exportovat audit logy', 403);
        }
        
        try {
            // Filtry
            $filters = [];
            if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
            if (!empty($_GET['username'])) $filters['username'] = $_GET['username'];
            if (!empty($_GET['action_type'])) $filters['action_type'] = $_GET['action_type'];
            if (!empty($_GET['severity'])) $filters['severity'] = $_GET['severity'];
            if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
            if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
            
            $csv = $this->auditLog->exportToCsv($filters);
            
            // Audit log pro export
            $this->auditLog->logDataExport(
                $_SESSION['user']['id'],
                $_SESSION['user']['username'],
                'audit_csv',
                substr_count($csv, "\n") - 1 // Počet řádků minus header
            );
            
            // Nastavení headers pro download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            // Clear any output buffers
            if (ob_get_length()) {
                ob_clean();
            }
            
            echo $csv;
            exit;
        } catch (Exception $e) {
            error_log('Chyba při exportu audit logů: ' . $e->getMessage());
            return $this->error('Chyba při exportu audit logů: ' . $e->getMessage());
        }
    }
    
    private function cleanupAuditLogs() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        // Pouze admin může mazat staré audit logy
        if ($_SESSION['user']['role'] !== 'admin') {
            return $this->error('Nedostatečná oprávnění - pouze administrátor může mazat audit logy', 403);
        }
        
        try {
            $daysToKeep = intval($_GET['days'] ?? 365);
            
            if ($daysToKeep < 30) {
                return $this->error('Minimální doba uchování je 30 dní');
            }
            
            $deletedCount = $this->auditLog->cleanupOldLogs($daysToKeep);
            
            return $this->success('Staré audit logy smazány', [
                'deleted_count' => $deletedCount,
                'days_to_keep' => $daysToKeep
            ]);
        } catch (Exception $e) {
            error_log('Chyba při mazání starých audit logů: ' . $e->getMessage());
            return $this->error('Chyba při mazání starých audit logů: ' . $e->getMessage());
        }
    }
    
    private function getDashboardStats() {
        if (!$this->isLoggedIn()) {
            return $this->error('Nepřihlášen', 401);
        }
        
        try {
            $stats = [];
            
            // Základní statistiky záznamů
            $recordStats = $this->record->getDashboardStats();
            $stats['records'] = $recordStats;
            
            // Statistiky uživatelů
            $userStats = $this->user->getDashboardStats();
            $stats['users'] = $userStats;
            
            // Trendy za posledních 30 dní
            $trends = $this->record->getTrends(30);
            $stats['trends'] = $trends;
            
            // Distribuce stavů
            $statusDistribution = $this->record->getStatusDistribution();
            $stats['status_distribution'] = $statusDistribution;
            
            // Finanční statistiky
            $financialStats = $this->record->getFinancialStats();
            $stats['financial'] = $financialStats;
            
            // Pokud je admin, přidáme audit statistiky
            if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
                $auditStats = $this->auditLog->getDashboardStats();
                $stats['audit'] = $auditStats;
            }
            
            return $this->success('Dashboard statistiky načteny', $stats);
        } catch (Exception $e) {
            error_log('Chyba při načítání dashboard statistik: ' . $e->getMessage());
            return $this->error('Chyba při načítání dashboard statistik: ' . $e->getMessage());
        }
    }
}

// Spuštění API
$api = new API();
$api->handleRequest();
?>