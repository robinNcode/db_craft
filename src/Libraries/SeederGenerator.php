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

    public function generateSeeders()
    {
        $tables = $this->getTables();
        $seederFileContent = [];

        foreach ($tables as $table) {
            $data = $this->getTableData($table);
            $seederFileContent = $this->generateSeederFile($table, $data);

            $this->saveSeederFile($table, $seederFileContent);
        }

        //dd($seederFileContent);
    }

    protected function getTables(): array
    {
        $tables = [];

        $result = $this->db->listTables();

        foreach ($result as $row) {
            $tables[] = $row;
        }

        return $tables;
    }

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
        

