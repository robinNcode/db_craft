<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\CLI\CLI;

/**
 * @class SeederGenerator
 * Handle all db collection and generate table data ...
 * @author MsM Robin
 * @package Robinncode\DbCraft\Libraries
 */
class SeederGenerator
{
    protected BaseConnection $db;
    private FileHandler $file;
    const MIGRATION_TABLE = 'migrations';

    public function __construct()
    {
        $this->db = db_connect();
        $this->file = new FileHandler();
    }

    /**
     * Generate Seeder files for CodeIgniter 4 based on connected database tables ...
     * @param $table_name
     * @return void
     */
    public function generateSeeders($table_name = null)
    {
        $tables = $this->getTables($table_name);

        if(empty($tables)){
            CLI::write("No table found. Check your database connection", 'red');
        }
        else{
            foreach ($tables as $table) {
                $data = $this->getTableData($table);
                $seederFileContent = $this->generateSeederFile($table, $data);

                $this->saveSeederFile($table, $seederFileContent);
            }
            CLI::write('Seeder files generated successfully!', 'green');
        }
    }

    /**
     * Getting tables or only a specific table from connected database ...
     * @param null $table_name
     * @return array
     */
    protected function getTables($table_name = null): array
    {
        $tables = $this->allTables();

        if (empty($table_name)) {
            return $tables;
        }
        else{
            if (in_array($table_name, $tables)) {
                return [$table_name];
            }
            else{
                CLI::write("Table '$table_name' not found. Check your table name", 'red');
                return [];
            }
        }
    }

    /**
     * Getting all tables from database ...
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

    /**
     * Getting table data ...
     * @param $table
     * @return array
     */
    protected function getTableData($table): array
    {
        $query = $this->db->table($table)->get();
        return $query->getResultArray();
    }

    /**
     * Generating seeder files ...
     * @param $table
     * @param $data
     * @return string
     */
    protected function generateSeederFile($table, $data): string
    {
        $className = $this->file->tableToSeederClassName($table);
        // Generate the Seeder file content based on the $table and $data
        // You can customize the Seeder file content generation according to your needs

        $seederFileContent = "\$" . $table . " = [\n";
        foreach ($data as $row) {
            $seederFileContent .= "            [";
            foreach ($row as $column => $value) {
                $seederFileContent .= "'$column' => " . var_export($value, true) . ",";
            }
            $seederFileContent .= "],\n";
        }
        $seederFileContent .= "        ];\n";

        $data = [
            '{name}' => $className,
            '{created_at}' => PRETTIFY_DATETIME,
            '{seeder}' => $seederFileContent,
            '{table}' => $table
        ];

        return $this->file->renderTemplate('seeder', $data);
    }

    /**
     * Saving the seeder file ...
     * @param $table
     * @param $seederFileContent
     * @return void
     */
    protected function saveSeederFile($table, $seederFileContent)
    {
        // Save the Seeder file to app/Database/Seeds folder
        $fileName = $this->file->tableToSeederClassName($table);
        $path = APPPATH . 'Database/Seeds/' . $fileName . ".php";

        if (!file_exists($path)) {
            helper('filesystem');
            write_file($path, $seederFileContent);
            CLI::write($table . " Seeder file generated!", 'yellow');
        } else {
            CLI::write($table . " Seeder file already exists!", 'red');
        }
    }
}
