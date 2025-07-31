<?php
require_once __DIR__ . '/../includes/config.php';

try {
    // Read the SQL files
    $sql = [];
    
    // Main schema
    $mainSchema = file_get_contents(__DIR__ . '/update_schema.sql');
    if ($mainSchema === false) {
        throw new Exception("Could not read update_schema.sql file");
    }
    $sql[] = $mainSchema;
    
    // Remember token migration
    $rememberTokenMigration = file_get_contents(__DIR__ . '/add_remember_token_columns.sql');
    if ($rememberTokenMigration !== false) {
        $sql[] = $rememberTokenMigration;
    }
    
    // Process all SQL files
    $queries = [];
    
    foreach ($sql as $sqlContent) {
        // Split the SQL file into individual queries
        $fileQueries = array_filter(
            array_map('trim', explode(';', $sqlContent)),
            function($query) {
                return !empty($query) && strpos($query, '--') !== 0;
            }
        );
        
        $queries = array_merge($queries, $fileQueries);
    }
    
    // Execute each query
    $db = getDBConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Database Update Results</h2>";
    echo "<pre>";
    
    foreach ($queries as $query) {
        try {
            $db->exec($query);
            echo "[SUCCESS] Executed: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '') . "\n";
        } catch (PDOException $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
            echo "Query: " . substr($query, 0, 200) . (strlen($query) > 200 ? '...' : '') . "\n\n";
        }
    }
    
    echo "</pre>";
    echo "<p>Database update completed. <a href='../'>Go to dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
