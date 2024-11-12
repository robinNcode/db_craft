<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\CLI\CLI;
use ReflectionException;

/**
 * SeederGenerator class to handle database table collection and generate Seeder files for CodeIgniter 4.
 * @package Robinncode\DbCraft\Libraries
 */
class SeederGenerator
{
    protected BaseConnection $db;
    private FileHandler $file;
    const MIGRATION_TABLE = 'migrations';
    const CHUNK_SIZE = 1000; // Number of rows per chunk

    public function __construct()
    {
        $this->db = db_connect();
        $this->file = new FileHandler();
    }

    /**
     * Generate Seeder files based on database tables.
     * @param string|null $table_name
     * @return void
     */
    public function generateSeeders($table_name = null)
    {
        $tables = $this->getTables($table_name);

        if (empty($tables)) {
            CLI::write("No table found. Check your database connection", 'red');
            return;
        }

        foreach ($tables as $table) {
            $this->createSeederFileWithChunks($table);
        }
    }

    /**
     * Process table data in chunks, and add progress tracking.
     * @param string $table
     * @return void
     */
    protected function createSeederFileWithChunks(string $table): void
    {
        $totalRows = $this->getTableRowCount($table);
        $chunkSize = self::CHUNK_SIZE;

        if($totalRows === 0) {
            CLI::write("Table '$table' is empty. Skipping...", 'yellow');
            return;
        }
        elseif ($totalRows < $chunkSize) {
            $chunkSize = $totalRows;
        }

        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $className = $this->file->tableToSeederClassName($table);
        $filePath = APPPATH . 'Database/Seeds/' . $className . ".php";

        // Initialize header and footer templates
        $headerTemplate = $this->getSeederHeaderTemplate($className, $table);
        $footerTemplate = $this->getSeederFooterTemplate($table);

        // Write header to file
        file_put_contents($filePath, $headerTemplate);

        // Generate seeder content in chunks
        for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
            $offset = $chunk * $chunkSize;
            $rows = $this->getTableDataChunk($table, $offset, $chunkSize);
            $chunkContent = '';

            foreach ($rows as $row) {
                $chunkContent .= "            [";
                foreach ($row as $column => $value) {
                    $chunkContent .= "'$column' => " . var_export($value, true) . ",";
                }
                $chunkContent .= "],\n";
            }

            // Append chunk data to the seeder file
            file_put_contents($filePath, $chunkContent, FILE_APPEND | LOCK_EX);

            // Display progress
            $progress = (($chunk + 1) * $chunkSize) / $totalRows * 100;
            CLI::write(sprintf("Progress: %.2f%% (%d/%d rows)", $progress, min(($chunk + 1) * $chunkSize, $totalRows), $totalRows), 'yellow');
        }

        // Write footer to finalize the file
        file_put_contents($filePath, $footerTemplate, FILE_APPEND | LOCK_EX);

        // Success message for each file generated
        CLI::write("Seeder file for table '$table' generated successfully!", 'green');
    }

    /**
     * Returns the header template with placeholders replaced.
     * @param string $className
     * @param string $table
     * @return string
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
     * @param string $table
     * @return string
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
     * @param string $table
     * @return int
     */
    protected function getTableRowCount(string $table): int
    {
        return $this->db->table($table)->countAllResults();
    }

    /**
     * Retrieves a chunk of data from the specified table.
     * @param string $table
     * @param int $offset
     * @param int $limit
     * @return array
     */
    protected function getTableDataChunk(string $table, int $offset, int $limit): array
    {
        return $this->db->table($table)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Get the list of tables or a specific table.
     * @param string|null $table_name
     * @return array
     */
    protected function getTables($table_name = null): array
    {
        $tables = $this->allTables();

        if (empty($table_name)) {
            return $tables;
        } elseif (in_array($table_name, $tables)) {
            return [$table_name];
        } else {
            CLI::write("Table '$table_name' not found. Check your table name", 'red');
            return [];
        }
    }

    /**
     * Fetch all tables from the database.
     * @return array
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
}