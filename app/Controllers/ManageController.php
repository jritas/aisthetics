<?php

final class ManageController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Προβολή σελίδας "Γενικές ρυθμίσεις"
    public function index(): void
    {
        $title = 'Γενικές ρυθμίσεις';
        include __DIR__ . '/../Views/manage/index.php';
    }

    // Λήψη πλήρους SQL backup της βάσης (route: ?r=manage&a=backup)
    public function backup(): void
    {
        $pdo = $this->pdo;

        // Καθάρισε τυχόν buffers για να μη μπλεχτεί με layout
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Όνομα βάσης
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) {
            $dbName = 'database';
        }

        $now      = date('Ymd_His');
        $filename = sprintf('%s_backup_%s.sql', $dbName, $now);

        // Headers για download
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        // Header του dump
        echo "-- Aesthetics CRM database backup\n";
        echo "-- Database: `{$dbName}`\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // Λίστα πινάκων
        $tables = [];
        $stmt   = $pdo->query('SHOW TABLES');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            // Δομή πίνακα
            echo "-- ----------------------------------------\n";
            echo "-- Table structure for table `{$table}`\n";

            $res       = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $res->fetch(PDO::FETCH_NUM);
            $createSql = $createRow[1] ?? '';

            echo "DROP TABLE IF EXISTS `{$table}`;\n";
            echo $createSql . ";\n\n";

            // Δεδομένα πίνακα
            echo "-- Data for table `{$table}`\n";
            $res = $pdo->query("SELECT * FROM `{$table}`");

            while ($row = $res->fetch(PDO::FETCH_NUM)) {
                $values = [];

                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($value);
                    }
                }

                echo "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n";
            }

            echo "\n\n";
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        exit;
    }
}