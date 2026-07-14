<?php
/**
 * Test for issue #15 — migration generator must emit 'default' values.
 * Exercises MigrationGenerator::generateField() with fake DESCRIBE rows,
 * without a live MySQL connection.
 *
 * Usage: php test_migration_defaults.php
 */

namespace CodeIgniter\CLI {
    class CLI
    {
        public static function write(string $t = '', $f = null, $b = null): void {}
        public static function error(string $t): void { fwrite(STDERR, $t . PHP_EOL); }
    }
}

namespace Config {
    class Database
    {
        public static function connect($group = null) { return null; }
    }
}

namespace {
    require __DIR__ . '/../src/Libraries/MigrationGenerator.php';

    use Robinncode\DbCraft\Libraries\MigrationGenerator;

    /** Fake connection returning canned DESCRIBE rows */
    class FakeDescribeDb
    {
        private array $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function query(string $sql): FakeDescribeResult { return new FakeDescribeResult($this->rows); }
    }

    class FakeDescribeResult
    {
        private array $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function getResult(): array { return $this->rows; }
    }

    function describeRow(string $field, string $type, string $null, ?string $default, string $extra = ''): object
    {
        return (object) ['Field' => $field, 'Type' => $type, 'Null' => $null, 'Key' => '', 'Default' => $default, 'Extra' => $extra];
    }

    $failures = 0;
    function check(string $label, bool $ok): void
    {
        global $failures;
        echo ($ok ? "  PASS" : "  FAIL") . "  $label\n";
        if (!$ok) $failures++;
    }

    echo "Running MigrationGenerator default-value tests (issue #15)...\n";

    // Instantiate without running the constructor (no live DB needed)
    $ref = new ReflectionClass(MigrationGenerator::class);
    $gen = $ref->newInstanceWithoutConstructor();

    $dbProp = $ref->getProperty('db');
    $dbProp->setAccessible(true);
    $dbProp->setValue($gen, new FakeDescribeDb([
        describeRow('id', 'int(11) unsigned', 'NO', null, 'auto_increment'),
        describeRow('status', "enum('active','inactive')", 'NO', 'active'),
        describeRow('score', 'int(11)', 'NO', '10'),
        describeRow('price', 'decimal(10,2)', 'NO', '0.00'),
        describeRow('notes', 'text', 'YES', null),
        describeRow('created_at', 'timestamp', 'YES', 'current_timestamp()'),
    ]));

    $method = $ref->getMethod('generateField');
    $method->setAccessible(true);
    $fields = $method->invoke($gen, 'fake_table');

    check("string default emitted ('active')", strpos($fields, "'default' => 'active',") !== false);
    check("integer default emitted (10)", strpos($fields, "'default' => 10,") !== false);
    check("decimal default emitted (0.00)", strpos($fields, "'default' => 0.00,") !== false);
    check("nullable column defaults to null", strpos($fields, "'default' => null,") !== false);
    check("auto_increment gets no default", !preg_match("/'id'.*?'default'/s", explode("'status'", $fields)[0]));
    check("current_timestamp() handled as raw string", strpos($fields, 'DEFAULT current_timestamp()') !== false);

    echo $failures === 0 ? "\nAll tests passed.\n" : "\n$failures test(s) FAILED.\n";
    exit($failures === 0 ? 0 : 1);
}
