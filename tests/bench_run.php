<?php
/**
 * Benchmark runner — executed once per (mode, rows) in a fresh PHP process
 * so memory_get_peak_usage() is not polluted by the other mode.
 *
 * Usage: php bench_run.php <legacy|generator> <rows>
 */

$mode = $argv[1] ?? 'generator';
$rows = (int) ($argv[2] ?? 10000);

$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_craft_bench';
@mkdir($tmpDir . '/Database/Seeds', 0777, true);
$dbPath = $tmpDir . DIRECTORY_SEPARATOR . "bench_{$rows}.sqlite";

putenv('BENCH_DB=' . $dbPath);
putenv('BENCH_APPPATH=' . $tmpDir . '/');

// ---- Seed the fixture database (only once per row count) ----
if (!is_file($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT, email TEXT, bio TEXT, balance REAL,
        is_active INTEGER, created_at TEXT
    )");
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO users (name, email, bio, balance, is_active, created_at)
                           VALUES (?, ?, ?, ?, ?, ?)");
    for ($i = 1; $i <= $rows; $i++) {
        $stmt->execute([
            "User Number {$i} O'Brien",
            "user{$i}@example.com",
            str_repeat("Lorem ipsum dolor sit amet {$i}. ", 5),
            $i * 1.37,
            $i % 2,
            date('Y-m-d H:i:s', 1700000000 + $i),
        ]);
    }
    $pdo->commit();
    unset($pdo);
}

require __DIR__ . '/stubs.php';

use Robinncode\DbCraft\Libraries\SeederGenerator;

/**
 * The pre-generator implementation (commit 5170804-era "load everything at
 * once" style, per generateSeederFile in be18205): fetch ALL rows into an
 * array and build the whole file content as one string in memory.
 */
class LegacySeederGenerator extends SeederGenerator
{
    protected function createSeederFileWithChunks(string $table): void
    {
        $data = $this->db->table($table)->get()->getResultArray(); // all rows at once

        $className = (new \Robinncode\DbCraft\Libraries\FileHandler())->tableToSeederClassName($table);
        $filePath = APPPATH . 'Database/Seeds/' . $className . '.php';

        $content = $this->getSeederHeaderTemplate($className, $table);
        foreach ($data as $row) {
            $content .= "            [";
            foreach ($row as $column => $value) {
                $content .= "'$column' => " . var_export($value, true) . ",";
            }
            $content .= "],\n";
        }
        $content .= $this->getSeederFooterTemplate($table);

        file_put_contents($filePath, $content);
    }
}

// ---- Run ----
gc_collect_cycles();
$start = microtime(true);

$gen = $mode === 'legacy' ? new LegacySeederGenerator() : new SeederGenerator();
$gen->generateSeeders('users');

$elapsed = microtime(true) - $start;
$peakMb = memory_get_peak_usage(true) / 1048576;

$seederFile = $tmpDir . '/Database/Seeds/UsersSeeder.php';
$fileMb = is_file($seederFile) ? filesize($seederFile) / 1048576 : 0;

echo json_encode([
    'mode'    => $mode,
    'rows'    => $rows,
    'time_s'  => round($elapsed, 3),
    'peak_mb' => round($peakMb, 2),
    'file_mb' => round($fileMb, 2),
]) . PHP_EOL;
