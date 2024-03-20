<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

/**
 * @class MigrationGenerator
 * Handle all db collection and generate table column ...
 * @author MsM Robin
 * @package Robinncode\DbCraft\Libraries
 */
class MigrationGenerator
{
    /**
     * @var array|BaseConnection|string|null
     */
    protected $db = null;
    const MIGRATION_TABLE = 'migrations';

    /**
     * DBHandler constructor...
     * @param string|null $group
     */
    public function __construct(string $group = null)
    {
        try {
            $this->db = Database::connect($group);
            $this->db->initialize();
        } catch (Throwable $exception) {
            CLI::error($exception->getMessage());
            die();
        }
    }

    /**
     * Generating all migration...
     * @return void
     */
    public function generateAllMigration(): void
    {
        $tables = $this->getTableNames();
        foreach ($tables as $table) {
            $tableInfo = $this->getTableInfos($table);
            
            if ($table === self::MIGRATION_TABLE) {
               continue;
            }
            
            $file = new FileHandler();
            $file->writeTable($table, $tableInfo['attributes'], $tableInfo['keys']);
        }
    }

    /**
     * Generate migration for a single table ...
     * @param $table
     * @return void
     */
    public function generateSingleMigration($table): void
    {
        $tableInfo = $this->getTableInfos($table);

        $file = new FileHandler();
        $file->writeTable($table, $tableInfo['attributes'], $tableInfo['keys']);
    }

    /**
     * Return a list of All tables
     * Name from a specific database group
     * or default on
     *
     * @return array
     */
    public function getTableNames(): array
    {
        $tables = $this->db->listTables() ?? [];

        if (empty($tables)) {
            CLI::error('No table found in database!');
            exit(1);
        }

        return $tables;
    }

    /**
     * return a list of all fields and
     * key generated from a table
     *
     * @param string $table
     *
     * @return array
     */
    public function getTableInfos(string $table): array
    {
        $fields = $this->generateField($table);

        $indexes = $this->generateKeys($table);

        $relations = $this->generateForeignKeys($table);

        return [
            'attributes' => $fields,
            'keys' => $indexes . "\n" . $relations
        ];
    }

    /**
     * Glue an array into a single string
     * @param array $arr
     * @param bool $is_assoc
     * @return string
     * @author MsM Robin
     */
    protected function getGluedString(array $arr, bool $is_assoc = false): string
    {
        //array consist of one element
        if (count($arr) == 1){
            return "'" . array_shift($arr) . "'";
        }
        else {
            $str = '';
            if (!$is_assoc) {
                foreach ($arr as $item) {
                    if (strlen($item) > 0)
                        $str .= "'$item', ";
                }
            } else {
                foreach ($arr as $index => $item) {
                    if (strlen($item) > 0)
                        $str .= "'$index' => '$item',";
                }
            }

            return "[ " . rtrim($str, ', ') . "]";
        }
    }

    /**
     * Generate Field array from a table
     * @param string $table
     * @return string|null
     */
    protected function generateField(string $table): ?string
    {
        $query = $this->db->query("DESCRIBE $table")->getResult();
        $fieldString = '';

        foreach ($query as $field) {

            // Check if the field has a default value of 'current_timestamp()' or other custom default value
            if ($field->Default === 'current_timestamp()' || $this->isCustomDefaultValue($field->Default)) {
                $fieldString .= "\n\t\t'$field->Field $field->Type NULL DEFAULT current_timestamp()',";
                continue;
            }

            $singleField = "\n\t\t'$field->Field' => [";
            //Type
            if (preg_match('/^([a-z]+)/', $field->Type, $matches) > 0)
                $singleField .= "\n\t\t\t'type' => '" . strtoupper($matches[1]) . "',";

            //Constraint
            if (preg_match('/\((.+)\)/', $field->Type, $matches) > 0) {
                //integer , varchar
                if (is_numeric($matches[1]))
                    $singleField .= "\n\t\t\t'constraint' => " . $matches[1] . ",";
                //float , double
                elseif (preg_match('/[\d]+\s?,[\d]+\s?/', $matches[1]) > 0)
                    $singleField .= "\n\t\t\t'constraint' => '" . $matches[1] . "',";
                //Enum Fields
                else {
                    $values = explode(',', str_replace("'", "", $matches[1]));

                    if (count($values) == 1)
                        $singleField .= "\n\t\t\t'constraint' => [" . $this->getGluedString($values) . "],";
                    else
                        $singleField .= "\n\t\t\t'constraint' => " . $this->getGluedString($values) . ",";
                }
            }

            //if field needs null
            $singleField .= "\n\t\t\t'null' => " . (($field->Null == 'YES') ? 'true,' : 'false,');
            //unsigned
            if (strpos($field->Type, 'unsigned') !== false)
                $singleField .= "\n\t\t\t'unsigned' => true,";

            //autoincrement
            if (strpos($field->Extra, 'auto_increment') !== false)
                $singleField .= "\n\t\t\t'auto_increment' => true,";

            $singleField .= "\n\t\t],";
            $fieldString .= $singleField;
        }

        return $fieldString;
    }

    /**
     * Check if the given default value is a custom default value
     * @param string|null $defaultValue
     * @return bool
     */
    protected function isCustomDefaultValue(?string $defaultValue): bool
    {
        // Implementing custom logic to check for other custom default values here
        // For example, you can check if the default value contains 'current_timestamp()' or not ...
        if($defaultValue === null) {
            return false;
        }

        return strpos($defaultValue, 'current_timestamp()') !== false;
    }

    /**
     * To generate keys from a table ...
     * @param string $table
     * @return string|null
     */
    protected function generateKeys(string $table): ?string
    {
        $index = $this->db->getIndexData($table);

        $keys = [];
        $keys['primary'] = '';
        $keys['foreign'] = '';
        $keys['unique'] = '';

        foreach ($index as $key) {
            switch ($key->type) {
                case 'PRIMARY': {
                        $keys['primary'] = "\n\t\t\$this->forge->addPrimaryKey(" .
                            $this->getGluedString($key->fields) . ");";
                        break;
                    }
                case 'UNIQUE': {
                        $keys['unique'] .= "\n\t\t\$this->forge->addUniqueKey(" .
                            $this->getGluedString($key->fields) . ");";
                        break;
                    }
                default: {
                        $keys['foreign'] .= "\n\t\t\$this->forge->addKey(" .
                            $this->getGluedString($key->fields) . ");";
                        break;
                    }
            }
        }
        return implode("\n", $keys);
    }

    /**
     * @param string $table
     * @return string|null
     */
    protected function generateForeignKeys(string $table): ?string
    {
        $keys = $this->db->getForeignKeyData($table);
        $keyArray = [];
        foreach ($keys as $key) {
            $columnName = $key->column_name[0];
            $foreignColumnName = $key->foreign_column_name[0];
            $foreignTableName = $key->foreign_table_name;
            $onDelete = $key->on_delete;
            $onUpdate = $key->on_update;

            $keyArray[] = "\n\t\t\$this->forge->addForeignKey('$columnName','$foreignTableName','$foreignColumnName','$onDelete','$onUpdate');";
        }

        return implode('', array_unique($keyArray));
    }
}
