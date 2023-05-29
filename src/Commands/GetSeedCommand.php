<?php namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use Robinncode\DbCraft\Libraries\SeederGenerator;

class GetSeedCommand extends BaseCommand
{
    protected $group       = 'seeder';
    protected $name        = 'get:seed';
    protected $description = 'Generate Seeder files for CodeIgniter 4 based on connected database tables.';

    /**
     * The Command's usage
     * @var string
     */
    protected $usage = 'get:seed [table_name]';


    public function run(array $params)
    {
        $table_name = array_shift($params);

        $generator = new SeederGenerator();
        $generator->generateSeeders($table_name);
    }
}
