<?php namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Libraries\SeederGenerator;

class GetSeedCommand extends BaseCommand
{
    protected $group       = 'seeder';
    protected $name        = 'get:seed';
    protected $description = 'Generate Seeder files for CodeIgniter 4 based on connected database tables.';

    public function run(array $params)
    {
        $generator = new SeederGenerator();
        $generator->generateSeeders();

        CLI::write('Seeder files generated successfully.', 'green');
    }
}
