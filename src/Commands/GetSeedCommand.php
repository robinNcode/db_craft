<?php namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
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
    protected $usage = 'get:seed [table_name] [--chunk chunk_size]';

    /**
     * The Command's arguments
     * @var array<string, string>
     */
    protected $arguments = [
        'table_name' => 'Optional table name to generate a single seeder.',
    ];

    /**
     * The Command's options
     * @var array<string, string>
     */
    protected $options = [
        '--chunk' => 'Number of rows fetched per chunk (default: 1000).',
    ];

    public function run(array $params)
    {
        $table_name = array_shift($params);

        $chunkSize = $params['chunk'] ?? CLI::getOption('chunk') ?? SeederGenerator::DEFAULT_CHUNK_SIZE;

        if (!is_numeric($chunkSize) || (int) $chunkSize < 1) {
            CLI::error('Invalid --chunk value. It must be a positive integer.');
            return;
        }

        $generator = new SeederGenerator((int) $chunkSize);
        $generator->generateSeeders($table_name);
    }
}
