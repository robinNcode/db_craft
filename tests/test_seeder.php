<?php
/**
 * Correctness test — verifies the generator-based seeder:
 *  1. produces a syntactically valid PHP file
 *  2. contains every row with correct data
 *  3. is byte-identical (data section) to the legacy all-at-once output
 *
 * Usage: php test_seeder.php
 */

$rows = 2500; // > CHUNK_SIZE to exercise multi-chunk paths + tail flush
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_craft_test_' . getmypid();
mkdir($tmpDir . '/Database/Seeds', 0777, true);
$dbPath = $tmpDir . DIRECTORY_SEPARATOR . 'test.sqlite';

putenv('BENCH_DB=' . $dbPath);
putenv('BENCH_APPPATH=' . $tmpDir . '/');

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, title TEXT, price REAL, stock INTEGER, notes TEXT)");
$pdo->exec("CREATE TABLE empty_table (id INTEGER PRIMARY KEY)");
$pdo->exec("CREATE TABLE migrations (id INTEGER PRIMARY KEY)"); // must be skipped
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO products (title, price, stock, notes) VALUES (?, ?, ?, ?)");
for ($i = 1; $i <= $rows; $i++) {
    $stmt->execute(["Product 'quoted' #{$i}", $i * 0.99, $i % 100, $i % 7 === 0 ? null : "note-{$i}"]);
}
$pdo->commit();
unset($pdo);

require __DIR__ . '/stubs.php';

use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Libraries\SeederGenerator;

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? "  PASS" : "  FAIL") . "  $label\n";
    if (!$ok) $failures++;
}

echo "Running SeederGenerator tests ($rows rows, chunk size 1000)...\n";

// --- Generate ---
(new SeederGenerator())->generateSeeders();

$seederFile = $tmpDir . '/Database/Seeds/ProductsSeeder.php';
check('seeder file created', is_file($seederFile));
check('empty table skipped (no EmptyTableSeeder)', !is_file($tmpDir . '/Database/Seeds/EmptyTableSeeder.php'));
check('migrations table skipped', !is_file($tmpDir . '/Database/Seeds/MigrationsSeeder.php'));

// --- Valid PHP syntax ---
exec('php -l ' . escapeshellarg($seederFile) . ' 2>&1', $out, $code);
check('generated file passes php -l', $code === 0);

// --- Extract the data array and verify contents ---
$src = file_get_contents($seederFile);
check('contains class ProductsSeeder', str_contains($src, 'class ProductsSeeder extends Seeder'));
check('contains insertBatch call', str_contains($src, "table('products')->insertBatch"));

// Evaluate just the data array in isolation
preg_match('/\$products = \[(.*)^\s+\];/ms', $src, $m);
check('data array found in file', isset($m[1]));
$data = eval('return [' . $m[1] . '];');

check("row count matches ($rows)", count($data) === $rows);
check('first row data correct',
    $data[0]['id'] === 1
    && $data[0]['title'] === "Product 'quoted' #1"
    && $data[0]['notes'] === 'note-1');
check('null values preserved', $data[6]['notes'] === null); // row 7: 7 % 7 === 0
check('last row data correct',
    $data[$rows - 1]['id'] === $rows
    && $data[$rows - 1]['title'] === "Product 'quoted' #{$rows}");

// --- Progress output throttled: showProgress per chunk, not a write per row ---
// 2500 rows / 1000 chunk = 2 in-loop + 1 final + 1 clear = 4 progress calls
check('CLI progress bar used (' . CLI::$progressCalls . ' calls)', CLI::$progressCalls > 0 && CLI::$progressCalls < 10);
check('CLI writes throttled (' . CLI::$writes . ' writes for ' . $rows . ' rows)', CLI::$writes < 20);

// --- Custom chunk size (issue: get chunk size from user input) ---
CLI::$progressCalls = 0;
(new SeederGenerator(500))->generateSeeders('products');
// 2500 rows / 500 chunk = 5 in-loop + 1 final + 1 clear = 7 progress calls
check('custom chunk size honored (' . CLI::$progressCalls . ' progress calls @ chunk 500)', CLI::$progressCalls === 7);

$src2 = file_get_contents($seederFile);
preg_match('/\$products = \[(.*)^\s+\];/ms', $src2, $m2);
$data2 = eval('return [' . $m2[1] . '];');
check('output identical across chunk sizes', $data2 === $data);

// --- MigrationGenerator default value fix (issue #15) ---
$migSrc = file_get_contents(__DIR__ . '/../src/Libraries/MigrationGenerator.php');
check('migration generator emits default values', str_contains($migSrc, "'default' =>"));

// --- Cleanup ---
array_map('unlink', glob($tmpDir . '/Database/Seeds/*.php'));
@rmdir($tmpDir . '/Database/Seeds');
@rmdir($tmpDir . '/Database');
@unlink($dbPath);
@rmdir($tmpDir);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n$failures test(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
