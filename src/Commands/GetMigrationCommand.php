<?php

namespace Robinncode\DbCraft\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Robinncode\DbCraft\Libraries\MigrationGenerator;
use Throwable;

class GetMigrationCommand extends BaseCommand
{
    protected $group = 'migrations';

    protected $name = 'get:migration';

    protected $description = 'Generates migration file(s) from existing database tables.';

    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'get:migration [table_name]';

    /**
     * The Command's arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'table_name' => 'Optional table name to generate a single migration. Generates all tables when omitted.',
    ];

    public function run(array $params)
    {
        $table = array_shift($params);

        // Validate table name before touching the database ...
        if ($table !== null && ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            CLI::error("Invalid table name '{$table}'. Use letters, numbers and underscores only.");
            CLI::write('Usage: ' . $this->usage, 'cyan');

            return;
        }

        try {
            $migration = new MigrationGenerator();

            if (empty($table)) {
                $migration->generateAllMigration();
            } else {
                $migration->generateSingleMigration($table);
            }
        } catch (Throwable $e) {
            CLI::error('Migration generation failed: ' . $e->getMessage());
        }
    }
}
