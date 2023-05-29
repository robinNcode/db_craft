<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\CLI\CLI;

class SeederGenerator
{
    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * Generate Seeder files for CodeIgniter 4 based on connected database tables ...
     * @param $table_name
     * @return void
     */
    public function generateSeeders($table_name = null)
    {
        $tables = $this->getTables($table_name);
        $seederFileContent = [];

        if(empty($tables)){
            CLI::write("No table found. Check your database connection", 'red');
        }
        else{
            foreach ($tables as $table) {
                $data = $this->getTableData($table);
                $seederFileContent = $this->generateSeederFile($table, $data);

                $this->saveSeederFile($table, $seederFileContent);
            }
            CLI::write('Seeder files generated successfully.', 'green');
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
            $tables[] = $row;
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
     * Convert table name to Seeder class name
     * @param $input
     * @return string
     */

    protected function tableToSeederClassName($input): string
    {
        $parts = explode('_', $input);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        $className .= 'Seeder';

        return $className;
    }

    protected function generateSeederFile($table, $data): string
    {
        $file = new FileHandler();
        $className = $this->tableToSeederClassName($table);
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
        $seederFileContent .= "        ];\n\n";

        $data = [
            '{name}' => $className,
            '{created_at}' => date("d F, Y h:i:s A"),
            '{seeder}' => $seederFileContent,
            '{table}' => $table
        ];

        return $file->renderTemplate('seeder', $data);
    }

    protected function saveSeederFile($table, $seederFileContent)
    {
        // Save the Seeder file to app/Database/Seeds folder
        $fileName = $this->tableToSeederClassName($table);
        $path = APPPATH . 'Database/Seeds/' . $fileName . ".php";

        if (!file_exists($path)) {
            helper('filesystem');
            write_file($path, $seederFileContent);
            CLI::write("Seeder file '$table' generated.", 'yellow');
        } else {
            CLI::write("Seeder file '$table' already exists.", 'red');
        }
    }
}
        

