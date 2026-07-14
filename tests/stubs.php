<?php
/**
 * Minimal CodeIgniter 4 stubs so SeederGenerator can run standalone
 * against a SQLite database for testing / benchmarking.
 */

namespace CodeIgniter\CLI {
    class CLI
    {
        public static int $writes = 0;
        public static int $progressCalls = 0;

        public static function write(string $text = '', string $foreground = null, string $background = null): void
        {
            self::$writes++;
            if (getenv('BENCH_VERBOSE')) {
                echo $text . PHP_EOL;
            }
        }

        /**
         * Mirrors CI4's CLI::showProgress($thisStep, $totalSteps).
         * Pass false to clear the bar.
         */
        public static function showProgress($thisStep = 1, int $totalSteps = 10): void
        {
            self::$progressCalls++;
            if (getenv('BENCH_VERBOSE') && $thisStep !== false) {
                $percent = (int) (($thisStep / $totalSteps) * 100);
                echo "[" . str_repeat('#', (int) ($percent / 10)) . str_repeat('.', 10 - (int) ($percent / 10)) . "] {$percent}%" . PHP_EOL;
            }
        }

        public static function error(string $text): void
        {
            fwrite(STDERR, $text . PHP_EOL);
        }

        public static function prompt(string $field, $options = null, $validation = null): string
        {
            return 'yes';
        }
    }
}

namespace CodeIgniter\Database {
    class FakeBuilder
    {
        private \PDO $pdo;
        private string $table;
        private ?int $limit = null;
        private int $offset = 0;
        private array $wheres = [];
        private ?string $orderBy = null;

        public function __construct(\PDO $pdo, string $table)
        {
            $this->pdo = $pdo;
            $this->table = $table;
        }

        public function limit(int $limit, int $offset = 0): self
        {
            $this->limit = $limit;
            $this->offset = $offset;
            return $this;
        }

        public function where(string $condition, $value): self
        {
            // Mirrors CI4's "field >" style conditions
            [$field, $op] = array_pad(explode(' ', trim($condition), 2), 2, '=');
            $this->wheres[] = ["\"$field\" $op ?", $value];
            return $this;
        }

        public function orderBy(string $field, string $direction = 'ASC'): self
        {
            $this->orderBy = "\"$field\" $direction";
            return $this;
        }

        public function countAllResults(): int
        {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM \"{$this->table}\"")->fetchColumn();
        }

        public function get(): FakeResult
        {
            $sql = "SELECT * FROM \"{$this->table}\"";
            $params = [];
            if ($this->wheres) {
                $sql .= ' WHERE ' . implode(' AND ', array_column($this->wheres, 0));
                $params = array_column($this->wheres, 1);
            }
            if ($this->orderBy) {
                $sql .= " ORDER BY {$this->orderBy}";
            }
            if ($this->limit !== null) {
                $sql .= " LIMIT {$this->limit} OFFSET {$this->offset}";
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return new FakeResult($stmt);
        }

        public function truncate(): bool
        {
            $this->pdo->exec("DELETE FROM \"{$this->table}\"");
            return true;
        }
    }

    class FakeResult
    {
        private \PDOStatement $stmt;

        public function __construct(\PDOStatement $stmt)
        {
            $this->stmt = $stmt;
        }

        public function getResultArray(): array
        {
            return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    class BaseConnection
    {
        private \PDO $pdo;

        public function __construct(string $sqlitePath)
        {
            $this->pdo = new \PDO('sqlite:' . $sqlitePath);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        public function table(string $name): FakeBuilder
        {
            return new FakeBuilder($this->pdo, $name);
        }

        public function listTables(): array
        {
            return $this->pdo
                ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        /**
         * Mirrors CI4's getIndexData(): array of objects with ->type and ->fields.
         * BENCH_NO_PK=1 forces the "no primary key" fallback path for benchmarking.
         */
        public function getIndexData(string $table): array
        {
            if (getenv('BENCH_NO_PK')) {
                return [];
            }

            $indexes = [];
            $stmt = $this->pdo->query("PRAGMA table_info(\"$table\")");
            $pkFields = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
                if ($col['pk'] > 0) {
                    $pkFields[] = $col['name'];
                }
            }
            if ($pkFields) {
                $indexes[] = (object) ['name' => 'PRIMARY', 'type' => 'PRIMARY', 'fields' => $pkFields];
            }
            return $indexes;
        }

        public function disableForeignKeyChecks(): void {}
        public function enableForeignKeyChecks(): void {}
    }
}

namespace {
    function db_connect()
    {
        return new \CodeIgniter\Database\BaseConnection(getenv('BENCH_DB'));
    }

    if (!defined('APPPATH')) {
        define('APPPATH', rtrim(getenv('BENCH_APPPATH'), '/\\') . DIRECTORY_SEPARATOR);
    }

    require __DIR__ . '/../src/Config.php';
    require __DIR__ . '/../src/Libraries/FileHandler.php';
    require __DIR__ . '/../src/Libraries/SeederGenerator.php';
}
