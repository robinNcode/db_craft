<?php

namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * SeederGenerator class to handle database table collection and generate Seeder files for CodeIgniter 4.
 */
class SeederGenerator
{
    protected BaseConnection $db;

    private FileHandler $file;

    const MIGRATION_TABLE = 'migrations';
    const DEFAULT_CHUNK_SIZE = 1000; // Default number of rows per chunk

    /**
     * Number of rows fetched from the database per chunk.
     * @var int
     */
    protected int $chunkSize;

    public function __construct(int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        try {
            $this->db = db_connect();
            $this->db->initialize();
        } catch (\Throwable $exception) {
            CLI::error('Database connection failed: ' . $exception->getMessage());
            CLI::write('Check your database settings in .env or app/Config/Database.php', 'yellow');
            exit(1);
        }

        $this->file = new FileHandler();
        $this->chunkSize = max(1, $chunkSize);
    }

    /**
     * Generate Seeder files based on database tables.
     *
     * @return void
     */
    public function generateSeeders(?string $table_name = null)
    {
        $tables = $this->getTables($table_name);

        if (empty($tables)) {
            return;
        }

        $generated = 0;

        foreach ($tables as $table) {
            if ($this->createSeederFileWithChunks($table)) {
                $generated++;
            }
        }

        CLI::write("Done! {$generated} seeder file(s) generated.", 'green');
    }

    /**
     * Process table data in chunks, and add progress tracking.
     * Returns true when a seeder file was written.
     */
    protected function createSeederFileWithChunks(string $table): bool
    {
        $totalRows = $this->getTableRowCount($table);

        if ($totalRows === 0) {
            CLI::write("  Skipped: '{$table}' (table is empty)", 'yellow');

            return false;
        }

        $className = $this->file->tableToSeederClassName($table);
        $filePath = APPPATH.'Database/Seeds/'.$className.'.php';

        // Open the file once and stream into it — re-opening per row is very slow
        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            CLI::error("  Failed: unable to write '{$filePath}'. Check folder permissions.");

            return false;
        }

        fwrite($handle, $this->getSeederHeaderTemplate($className, $table));

        CLI::write("  Generating: {$className}.php ('{$table}', {$totalRows} rows)", 'cyan');

        // Stream rows via generator — only one chunk lives in memory at a time
        $processed = 0;
        $buffer = '';
        foreach ($this->yieldTableRows($table) as $row) {
            $buffer .= "            [";
            foreach ($row as $column => $value) {
                $buffer .= "'$column' => " . var_export($value, true) . ",";
            }
            $buffer .= "],\n";

            $processed++;

            // Flush the buffer and update the progress bar once per chunk, not per row
            if ($processed % $this->chunkSize === 0) {
                fwrite($handle, $buffer);
                $buffer = '';
                CLI::showProgress($processed, $totalRows);
            }
        }

        // Write any remaining rows and the footer to finalize the file
        fwrite($handle, $buffer . $this->getSeederFooterTemplate($table));
        fclose($handle);

        // Complete and clear the progress bar
        CLI::showProgress($totalRows, $totalRows);
        CLI::showProgress(false);

        CLI::write("  Created: {$className}.php ({$processed}/{$totalRows} rows)", 'green');

        return true;
    }

    /**
     * Returns the header template with placeholders replaced.
     */
    protected function getSeederHeaderTemplate(string $className, string $table): string
    {
        $currentTime = date('Y-m-d H:i:s');

        return <<<EOT
<?php namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use ReflectionException;

/**
 * Seeder for the `$table` table.
 * @class $className
 * @generated_by RobinNcode\\db_craft
 * @package App\\Database\\Seeds
 * @extend CodeIgniter\\Database\\Seeder
 * @generated_at {$currentTime}
 */

class $className extends Seeder
{
    /**
     * @throws ReflectionException
     */
    public function run(): void
    {
        // Disable foreign key checks
        \$this->db->disableForeignKeyChecks();

        // Table Data
        \$$table = [
EOT;
    }

    /**
     * Returns the footer template with placeholders replaced.
     */
    protected function getSeederFooterTemplate(string $table): string
    {
        return <<<EOT
        ];

        // Clean up the table before seeding
        \$this->db->table('$table')->truncate();

        // Insert data into the table
        try {
            \$this->db->table('$table')->insertBatch(\$$table);
        } catch (ReflectionException \$e) {
            throw new ReflectionException(\$e->getMessage());
        }

        // Enable foreign key checks
        \$this->db->enableForeignKeyChecks();
    }
}
EOT;
    }

    /**
     * Get total row count for the table.
     */
    protected function getTableRowCount(string $table): int
    {
        return $this->db->table($table)->countAllResults();
    }

    /**
     * PHP Generator that yields rows from the table one at a time,
     * fetching from the database in chunks to keep memory usage flat.
     *
     * Uses keyset pagination (WHERE pk > last ORDER BY pk) when the table
     * has a single-column primary key — O(n) instead of the O(n²) row
     * scanning that LIMIT/OFFSET causes on large tables. Falls back to
     * OFFSET pagination when no usable primary key exists.
     *
     * @param string $table
     * @return \Generator<int, array>
     */
    protected function yieldTableRows(string $table): \Generator
    {
        $primaryKey = $this->getPrimaryKey($table);

        if ($primaryKey !== null) {
            yield from $this->yieldRowsByKeyset($table, $primaryKey);
        } else {
            yield from $this->yieldRowsByOffset($table);
        }
    }

    /**
     * Stream rows ordered by primary key, seeking past the last seen key.
     * Each chunk query is index-driven regardless of table size.
     * @param string $table
     * @param string $primaryKey
     * @return \Generator<int, array>
     */
    protected function yieldRowsByKeyset(string $table, string $primaryKey): \Generator
    {
        $lastKey = null;

        do {
            $builder = $this->db->table($table)
                ->orderBy($primaryKey, 'ASC')
                ->limit($this->chunkSize);

            if ($lastKey !== null) {
                $builder->where($primaryKey . ' >', $lastKey);
            }

            $rows = $builder->get()->getResultArray();

            foreach ($rows as $row) {
                yield $row;
            }

            if (!empty($rows)) {
                $lastKey = end($rows)[$primaryKey];
            }
        } while (count($rows) === $this->chunkSize);
    }

    /**
     * Fallback: stream rows with LIMIT/OFFSET chunks (tables without a
     * single-column primary key). Memory stays flat but large offsets
     * get progressively slower on big tables.
     * @param string $table
     * @return \Generator<int, array>
     */
    protected function yieldRowsByOffset(string $table): \Generator
    {
        $offset = 0;

        do {
            $rows = $this->db->table($table)
                ->limit($this->chunkSize, $offset)
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                yield $row;
            }

            $offset += $this->chunkSize;
        } while (count($rows) === $this->chunkSize);
    }

    /**
     * Detect a single-column primary key for keyset pagination.
     * @param string $table
     * @return string|null
     */
    protected function getPrimaryKey(string $table): ?string
    {
        try {
            foreach ($this->db->getIndexData($table) as $index) {
                if (strtoupper($index->type) === 'PRIMARY' && count($index->fields) === 1) {
                    return $index->fields[0];
                }
            }
        } catch (\Throwable $e) {
            // Driver couldn't report index data — fall back to offset pagination
        }

        return null;
    }

    /**
     * Get the list of tables or a specific table.
     *
     * @param  string|null  $table_name
     */
    protected function getTables($table_name = null): array
    {
        $tables = $this->allTables();

        if (empty($tables)) {
            CLI::error('No tables found in database!');
            CLI::write('Check your database connection settings and make sure the database is not empty.', 'yellow');

            return [];
        }

        if (empty($table_name)) {
            return $tables;
        }

        if (in_array($table_name, $tables, true)) {
            return [$table_name];
        }

        CLI::error("Table '{$table_name}' not found in database.");
        CLI::write('Available tables: ' . implode(', ', $tables), 'yellow');

        return [];
    }

    /**
     * Fetch all tables from the database.
     */
    protected function allTables(): array
    {
        $tables = [];
        $result = $this->db->listTables();
        foreach ($result as $row) {
            if ($row !== self::MIGRATION_TABLE) {
                $tables[] = $row;
            }
        }

        return $tables;
    }

    /**
     * Getting table data ...
     */
    protected function getTableData($table): array
    {
        $query = $this->db->table($table)->get();

        return $query->getResultArray();
    }

    /**
     * Generating seeder files ...
     */
    protected function generateSeederFile($table, $data): string
    {
        $className = $this->file->tableToSeederClassName($table);
        // Generate the Seeder file content based on the $table and $data
        // You can customize the Seeder file content generation according to your needs

        $seederFileContent = '$'.$table." = [\n";
        foreach ($data as $row) {
            $seederFileContent .= '            [';
            foreach ($row as $column => $value) {
                $seederFileContent .= "'$column' => ".var_export($value, true).',';
            }
            $seederFileContent .= "],\n";
        }
        $seederFileContent .= "        ];\n";

        $data = [
            '{name}' => $className,
            '{created_at}' => PRETTIFY_DATETIME,
            '{seeder}' => $seederFileContent,
            '{table}' => $table,
        ];

        return $this->file->renderTemplate('seeder', $data);
    }

    /**
     * Saving the seeder file ...
     *
     * @return void
     */
    protected function saveSeederFile($table, $seederFileContent)
    {
        // Save the Seeder file to app/Database/Seeds folder
        $fileName = $this->file->tableToSeederClassName($table);
        $path = APPPATH.'Database/Seeds/'.$fileName.'.php';

        if (! file_exists($path)) {
            helper('filesystem');
            write_file($path, $seederFileContent);
            CLI::write($table.' Seeder file generated!', 'yellow');
        } else {
            CLI::write($table.' Seeder file already exists!', 'red');
        }
    }
}
