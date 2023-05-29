<?php namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Libraries\MigrationGenerator;

class GetMigrationCommand extends BaseCommand
{
    protected $group       = 'migrations';
    protected $name        = 'get:migration';
    protected $description = 'Generates migration file';

    public function run(array $params)
    {
        $table = array_shift($params);

        $migration = new MigrationGenerator();

        if (empty($table)) {
            $migration->generateAllMigration();
        } else {
            $migration->generateSingleMigration($table);

        }
    }
}
