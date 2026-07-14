<?php

namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Libraries\SeederGenerator;
use Throwable;

class GetSeedCommand extends BaseCommand
{
    protected $group = 'seeder';

    protected $name = 'get:seed';

    protected $description = 'Generates seeder file(s) from existing database table data.';

    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'get:seed [table_name] [--chunk chunk_size]';

    /**
     * The Command's arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'table_name' => 'Optional table name to generate a single seeder. Generates all tables when omitted.',
    ];

    /**
     * The Command's options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--chunk' => 'Number of rows fetched per chunk (default: 1000).',
    ];

    public function run(array $params)
    {
        $table_name = $params[0] ?? null;

        // Validate table name before touching the database ...
        if ($table_name !== null && ! preg_match('/^[A-Za-z0-9_]+$/', $table_name)) {
            CLI::error("Invalid table name '{$table_name}'. Use letters, numbers and underscores only.");
            CLI::write('Usage: ' . $this->usage, 'cyan');

            return;
        }

        $chunkSize = $params['chunk'] ?? CLI::getOption('chunk') ?? SeederGenerator::DEFAULT_CHUNK_SIZE;

        if (! is_numeric($chunkSize) || (int) $chunkSize < 1) {
            CLI::error("Invalid --chunk value '{$chunkSize}'. It must be a positive integer.");
            CLI::write('Usage: ' . $this->usage, 'cyan');

            return;
        }

        try {
            $generator = new SeederGenerator((int) $chunkSize);
            $generator->generateSeeders($table_name);
        } catch (Throwable $e) {
            CLI::error('Seeder generation failed: ' . $e->getMessage());
        }
    }
}
