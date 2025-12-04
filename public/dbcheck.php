<?php
declare(strict_types=1);
require __DIR__ . '/../app/Config/db.php';
echo $pdo->query('SELECT 1')->fetchColumn() ? 'DB OK' : 'DB FAIL';
