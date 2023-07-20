<?php

namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

/**
 * @class DBHandler
 * Handle all db collection and table column generate
 * @author MsM Robin
 * @package Robinncode\DbCraft\Libraries
 */
class MigrationGenerator
{
    /**
     * @var array|BaseConnection|string|null
     */
    protected $db = null;

    /**
     * DBHandler constructor.
     *
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
     *
     */
    public function generateAllMigration(): void
    {
        $tables = $this->getTableNames();
        foreach ($tables as $table) {
            $tableInfo = $this->getTableInfos($table);

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
     * Glue a array into a single string
     *
     * @param array $arr
     *
     * @param bool $is_assoc
     *
     * @return string
     * @author hafijul233
     *
     */
    protected function getGluedString(array $arr, bool $is_assoc = false): string
    {

        //array consist of one element
        if (count($arr) == 1)
            return "'" . strval(array_shift($arr)) . "'";

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

        // Get default values from information_schema
        $defaultValues = $this->getDefaultValues($table);

        foreach ($query as $field) {
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

            // Fetch and add the default value
            $default = isset($defaultValues[$field->Field]) ? $defaultValues[$field->Field] : null;

            if (!is_null($default)) {
                if (strpos($default, 'current_timestamp()') !== false) {
                    // If the default value is 'current_timestamp()', handle it accordingly.
                    $singleField .= "\n\t\t\t'default' => \$this->db->query('SELECT current_timestamp() AS time')->getRow()->time,";
                } else {
                    $singleField .= "\n\t\t\t'default' => '$default',";
                }
            }

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
        foreach ($keys as $key)
            $keyArray[] = "\n\t\t\$this->forge->addForeignKey('$key->column_name','$key->foreign_table_name','$key->foreign_column_name','CASCADE','CASCADE');";

        return implode('', array_unique($keyArray));
    }

    /**
     * Get default values for each column in the table from information_schema
     * @param string $table
     * @return array
     */
    protected function getDefaultValues(string $table): array
    {
        $query = $this->db->query("SELECT DISTINCT COLUMN_NAME, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_NAME = '$table'")->getResultArray();

        $defaultValues = [];
        foreach ($query as $row) {
            $columnName = $row['COLUMN_NAME'];
            $columnDefault = $row['COLUMN_DEFAULT'];

            // Exclude columns with NULL default values
            if ($columnDefault === 'NULL' || $columnDefault === null) {
                $defaultValues[$columnName] = null;
            } elseif (strpos($columnDefault, 'current_timestamp()') !== false) {
                // Handle current_timestamp() default value
                $defaultValues[$columnName] = 'current_timestamp()';
            } else {
                // Remove quotes from the default value if present
                $defaultValues[$columnName] = trim($columnDefault, "'");
            }
        }

        return $defaultValues;
    }

    // Ok, we have modify the generateField method. If there is any column has default value current timestamp then the syntax will be:
    //     "$this->forge->addField("updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");"
        
    //     not this:
    //     'updated_at' => [
    //                     'type' => 'TIMESTAMP', // Change the data type according to your column type (e.g., DATETIME, TIMESTAMP, etc.)
    //                     'default' => 'CURRENT_TIMESTAMP', // Set the current timestamp as the default value
    //                 ],
}
