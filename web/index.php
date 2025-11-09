<?php
require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/var/log/apache2/php_errors.log');

$maxTries = 20;
$retryDelay = 500000;
$db = null;
$lastError = null;

for ($i = 0; $i < $maxTries; $i++) {
    try {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbUser = getenv('DB_USER') ?: 'webuser';
        $dbPass = getenv('DB_PASS') ?: 'Passord123';
        $dbName = getenv('DB_NAME') ?: 'varehusdb';
        
        $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        
        if ($db->connect_errno === 0) {
            $db->set_charset('utf8mb4');
            break;
        }
        
        $lastError = $db->connect_error;
        $db->close();
        $db = null;
        
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        error_log("Database connection attempt " . ($i + 1) . " failed: " . $lastError);
    }
    
    if ($i < $maxTries - 1) {
        usleep($retryDelay);
    }
}

if (!$db || $db->connect_errno) {
    http_response_code(503);
    die("Kunne ikke koble til database. Vennligst prøv igjen senere.");
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];
    
    if ($action === 'tables') {
        $tables = [];
        $result = $db->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            $result->free();
        }
        echo json_encode(['success' => true, 'tables' => $tables]);
        $db->close();
        exit;
    }
    
    if ($action === 'table_data') {
        $tableName = $_GET['table'] ?? '';
        $search = $_GET['search'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $offset = (int)($_GET['offset'] ?? 0);
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            echo json_encode(['success' => false, 'error' => 'Ugyldig tabellnavn']);
            $db->close();
            exit;
        }
        
        $columns = [];
        $result = $db->query("SHOW COLUMNS FROM `$tableName`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            $result->free();
        }
        
        $whereClause = '';
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $conditions = [];
            foreach ($columns as $col) {
                $conditions[] = "`$col` LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
            }
            $whereClause = "WHERE " . implode(" OR ", $conditions);
        }
        
        $countQuery = "SELECT COUNT(*) as total FROM `$tableName` $whereClause";
        $countStmt = $db->prepare($countQuery);
        if (!empty($search) && $countStmt) {
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $totalRows = $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            $result = $db->query($countQuery);
            $totalRows = $result->fetch_assoc()['total'];
            $result->free();
        }
        
        $query = "SELECT * FROM `$tableName` $whereClause LIMIT ? OFFSET ?";
        $stmt = $db->prepare($query);
        $data = [];
        
        if ($stmt) {
            if (!empty($search)) {
                $params[] = $limit;
                $params[] = $offset;
                $types .= 'ii';
                $stmt->bind_param($types, ...$params);
            } else {
                $stmt->bind_param('ii', $limit, $offset);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            $stmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'columns' => $columns,
            'data' => $data,
            'total' => (int)$totalRows,
            'limit' => $limit,
            'offset' => $offset
        ]);
        $db->close();
        exit;
    }
    
    if ($action === 'search') {
        $searchTerm = $_GET['q'] ?? '';
        $results = [];
        
        if (!empty($searchTerm)) {
            $tablesResult = $db->query("SHOW TABLES");
            $tables = [];
            while ($row = $tablesResult->fetch_row()) {
                $tables[] = $row[0];
            }
            
            foreach ($tables as $table) {
                $columnsResult = $db->query("SHOW COLUMNS FROM `$table`");
                $columns = [];
                while ($col = $columnsResult->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
                
                $conditions = [];
                $params = [];
                foreach ($columns as $col) {
                    $conditions[] = "`$col` LIKE ?";
                    $params[] = "%$searchTerm%";
                }
                
                $query = "SELECT * FROM `$table` WHERE " . implode(" OR ", $conditions) . " LIMIT 10";
                $stmt = $db->prepare($query);
                if ($stmt) {
                    $types = str_repeat('s', count($params));
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $tableResults = [];
                    while ($row = $result->fetch_assoc()) {
                        $tableResults[] = $row;
                    }
                    
                    if (!empty($tableResults)) {
                        $results[$table] = [
                            'count' => count($tableResults),
                            'data' => $tableResults,
                            'columns' => $columns
                        ];
                    }
                    
                    $stmt->close();
                }
            }
        }
        
        echo json_encode(['success' => true, 'results' => $results]);
        $db->close();
        exit;
    }
}

$db->close();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Varehus Database - Moderne Søk</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .search-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .search-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 16px;
            border: 2px solid #dee2e6;
            border-radius: 50px;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 15px 30px;
            font-size: 16px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .content {
            padding: 30px;
        }
        
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .table-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .table-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .table-card.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table-card h3 {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .pagination button {
            padding: 10px 20px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .stat-card h4 {
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            header h1 {
                font-size: 1.8em;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🏪 Varehus Database</h1>
        </header>
        
        <div class="search-section">
            <div class="search-box">
                <input type="text" 
                       class="search-input" 
                       id="searchInput" 
                       placeholder="Søk i alle tabeller...">
                <button class="btn btn-primary" onclick="performSearch()">Søk</button>
                <button class="btn btn-secondary" onclick="clearSearch()">Tøm</button>
            </div>
        </div>
        
        <div class="content">
            <div id="tablesContainer" class="tables-grid">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Laster tabeller...</p>
                </div>
            </div>
            
            <div id="dataContainer" style="display: none;">
                <div class="stats" id="statsContainer"></div>
                <div style="overflow-x: auto;">
                    <table class="data-table" id="dataTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination" id="paginationContainer"></div>
            </div>
            
            <div id="searchResults" style="display: none;"></div>
        </div>
    </div>
    
    <script>
        let currentTable = null;
        let currentPage = 0;
        const pageSize = 50;
        let totalRows = 0;
        
        window.addEventListener('DOMContentLoaded', () => {
            loadTables();
        });
        
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        async function loadTables() {
            try {
                const response = await fetch('?action=tables');
                const data = await response.json();
                
                if (data.success) {
                    displayTables(data.tables);
                }
            } catch (error) {
                console.error('Feil ved lasting av tabeller:', error);
                document.getElementById('tablesContainer').innerHTML = 
                    '<div class="error">Kunne ikke laste tabeller. Prøv å oppdatere siden.</div>';
            }
        }
        
        function displayTables(tables) {
            const container = document.getElementById('tablesContainer');
            container.innerHTML = tables.map(table => `
                <div class="table-card" onclick="loadTableData('${table}')">
                    <h3>${table}</h3>
                    <p>Klikk for å vise data</p>
                </div>
            `).join('');
        }
        
        async function loadTableData(tableName, search = '', page = 0) {
            currentTable = tableName;
            currentPage = page;
            
            const container = document.getElementById('tablesContainer');
            const dataContainer = document.getElementById('dataContainer');
            const searchResults = document.getElementById('searchResults');
            
            container.querySelectorAll('.table-card').forEach(card => {
                card.classList.remove('active');
                if (card.querySelector('h3').textContent === tableName) {
                    card.classList.add('active');
                }
            });
            
            dataContainer.style.display = 'block';
            searchResults.style.display = 'none';
            document.getElementById('tableBody').innerHTML = 
                '<tr><td colspan="100%" class="loading"><div class="spinner"></div><p>Laster data...</p></td></tr>';
            
            try {
                const offset = page * pageSize;
                const url = `?action=table_data&table=${encodeURIComponent(tableName)}&limit=${pageSize}&offset=${offset}${search ? '&search=' + encodeURIComponent(search) : ''}`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayTableData(data);
                    totalRows = data.total;
                } else {
                    document.getElementById('tableBody').innerHTML = 
                        '<tr><td colspan="100%" class="error">Kunne ikke laste data.</td></tr>';
                }
            } catch (error) {
                console.error('Feil ved lasting av data:', error);
                document.getElementById('tableBody').innerHTML = 
                    '<tr><td colspan="100%" class="error">Kunne ikke laste data. Prøv igjen.</td></tr>';
            }
        }
        
        function displayTableData(data) {
            const thead = document.getElementById('tableHead');
            const tbody = document.getElementById('tableBody');
            const stats = document.getElementById('statsContainer');
            
            stats.innerHTML = `
                <div class="stat-card">
                    <h4>Tabell</h4>
                    <div class="number">${currentTable}</div>
                </div>
                <div class="stat-card">
                    <h4>Totalt antall rader</h4>
                    <div class="number">${data.total.toLocaleString('no-NO')}</div>
                </div>
                <div class="stat-card">
                    <h4>Viser</h4>
                    <div class="number">${data.data.length}</div>
                </div>
            `;
            
            thead.innerHTML = '<tr>' + data.columns.map(col => `<th>${col}</th>`).join('') + '</tr>';
            
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="100%" class="no-results">Ingen data funnet</td></tr>';
            } else {
                tbody.innerHTML = data.data.map(row => {
                    return '<tr>' + data.columns.map(col => {
                        const value = row[col] !== null ? row[col] : '<em>NULL</em>';
                        return `<td>${escapeHtml(String(value))}</td>`;
                    }).join('') + '</tr>';
                }).join('');
            }
            
            displayPagination(data.total, data.offset, data.limit);
        }
        
        function displayPagination(total, offset, limit) {
            const container = document.getElementById('paginationContainer');
            const totalPages = Math.ceil(total / limit);
            const currentPageNum = Math.floor(offset / limit);
            
            let html = '';
            
            html += `<button ${currentPageNum === 0 ? 'disabled' : ''} onclick="loadTableData('${currentTable}', '${document.getElementById('searchInput').value}', ${currentPageNum - 1})">Forrige</button>`;
            
            html += `<span style="padding: 10px 20px;">Side ${currentPageNum + 1} av ${totalPages}</span>`;
            
            html += `<button ${currentPageNum >= totalPages - 1 ? 'disabled' : ''} onclick="loadTableData('${currentTable}', '${document.getElementById('searchInput').value}', ${currentPageNum + 1})">Neste</button>`;
            
            container.innerHTML = html;
        }
        
        async function performSearch() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            
            if (!searchTerm) {
                if (currentTable) {
                    loadTableData(currentTable, '', 0);
                }
                return;
            }
            
            if (currentTable) {
                loadTableData(currentTable, searchTerm, 0);
            } else {
                try {
                    const response = await fetch(`?action=search&q=${encodeURIComponent(searchTerm)}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        displaySearchResults(data.results, searchTerm);
                    }
                } catch (error) {
                    console.error('Søkefeil:', error);
                }
            }
        }
        
        function displaySearchResults(results, searchTerm) {
            const container = document.getElementById('searchResults');
            const dataContainer = document.getElementById('dataContainer');
            
            dataContainer.style.display = 'none';
            container.style.display = 'block';
            
            if (Object.keys(results).length === 0) {
                container.innerHTML = `<div class="no-results">Ingen resultater funnet for "${searchTerm}"</div>`;
                return;
            }
            
            let html = `<h2 style="margin-bottom: 20px;">Søkeresultater for "${searchTerm}"</h2>`;
            
            for (const [table, result] of Object.entries(results)) {
                html += `
                    <div style="margin-bottom: 30px;">
                        <h3 style="color: #667eea; margin-bottom: 10px;">${table} (${result.count} resultater)</h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>${result.columns.map(col => `<th>${col}</th>`).join('')}</tr>
                                </thead>
                                <tbody>
                                    ${result.data.map(row => `
                                        <tr onclick="loadTableData('${table}')" style="cursor: pointer;">
                                            ${result.columns.map(col => `<td>${escapeHtml(String(row[col] || ''))}</td>`).join('')}
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            if (currentTable) {
                loadTableData(currentTable, '', 0);
            } else {
                document.getElementById('searchResults').style.display = 'none';
                document.getElementById('dataContainer').style.display = 'none';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>