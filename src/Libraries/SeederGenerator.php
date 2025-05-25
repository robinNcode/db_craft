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

    const CHUNK_SIZE = 1000; // Number of rows per chunk

    public function __construct()
    {
        $this->db = db_connect();
        $this->file = new FileHandler;
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
            CLI::write('No table found. Check your database connection', 'red');

            return;
        }

        foreach ($tables as $table) {
            $this->createSeederFileWithChunks($table);
        }
    }

    /**
     * Process table data in chunks, and add progress tracking.
     */
    protected function createSeederFileWithChunks(string $table): void
    {
        $totalRows = $this->getTableRowCount($table);
        $chunkSize = self::CHUNK_SIZE;

        if ($totalRows === 0) {
            CLI::write("Table '$table' is empty. Skipping...", 'yellow');

            return;
        } elseif ($totalRows < $chunkSize) {
            $chunkSize = $totalRows;
        }

        $totalChunks = (int) ceil($totalRows / $chunkSize);
        $className = $this->file->tableToSeederClassName($table);
        $filePath = APPPATH.'Database/Seeds/'.$className.'.php';

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
                $chunkContent .= '            [';
                foreach ($row as $column => $value) {
                    $chunkContent .= "'$column' => ".var_export($value, true).',';
                }
                $chunkContent .= "],\n";
            }

            // Append chunk data to the seeder file
            file_put_contents($filePath, $chunkContent, FILE_APPEND | LOCK_EX);

            // Display progress
            $progress = (($chunk + 1) * $chunkSize) / $totalRows * 100;
            CLI::write(sprintf('Progress: %.2f%% (%d/%d rows)', $progress, min(($chunk + 1) * $chunkSize, $totalRows), $totalRows), 'yellow');
        }

        // Write footer to finalize the file
        file_put_contents($filePath, $footerTemplate, FILE_APPEND | LOCK_EX);

        // Success message for each file generated
        CLI::write("Seeder file for table '$table' generated successfully!", 'green');
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
     * Retrieves a chunk of data from the specified table.
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
     *
     * @param  string|null  $table_name
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
