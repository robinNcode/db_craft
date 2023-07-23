<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Config;

class FileHandler
{
    private Config $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Convert table name to file class name
     * @param $input
     * @return string
     */

    public function tableToSeederClassName($input): string
    {
        $parts = explode('_', $input);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        $className .= 'Seeder';

        return $className;
    }

    /**
     * Table to migration class name
     * @param $input
     * @return string
     */
    public function tableToMigrationClassName($input): string
    {
        $parts = explode('_', $input);
        $className = 'Create';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        $className .= 'Table';

        return $className;
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     */
    public function renderTemplate(string $template, array $data): string
    {
        $templateDir = realpath(__DIR__ . '/../Templates/') . '/';
        $skeleton = file_get_contents($templateDir . $template . '.txt');

        return str_replace(array_keys($data), array_values($data), $skeleton);
    }

    /**
     * @param string $table
     * @param string $attributes
     * @param string $keys
     */
    public function writeTable(string $table, string $attributes, string $keys)
    {
        helper('inflector');
        $fileName = date('Y-m-d-His') . '_create_' . $table . '_table.php';
        $targetDir = ROOTPATH . 'app/Database/Migrations/';
        $filePath = $targetDir . $fileName;

        $replace = ['{migrate}', '{fields}', '{keys}', '{table}'];

        $with = [$attributes, $keys, $table];

        $data = [
            '{name}' => $this->tableToMigrationClassName($table),
            '{created_at}' => PRETTIFY_DATETIME,
            '{attributes}' => $attributes,
            '{keys}' => $keys,
            '{table}' => $table
        ];

        $finalFile = $this->renderTemplate('migration', $data);

        CLI::write($fileName . " file is creating...", 'yellow');
        if(file_put_contents($filePath, $finalFile)){
            CLI::write($fileName . " file created!", 'green');
        }
        else{
            CLI::error($fileName . " failed to create file!");
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    public function checkFileExist(string $path): bool
    {
        if (is_file($path)) {
            $permission = CLI::prompt("File already exists.Overwrite? ", ['yes', 'no'], 'required|in_list[yes,no]');
            if ($permission == 'no') {
                CLI::error("Task Cancelled.");
                exit(1);
            }
        }
        return true;
    }
}