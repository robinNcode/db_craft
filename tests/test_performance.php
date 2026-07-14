<?php

/**
 * Generates 10 million numbers using yield (Generator).
 */
function generatorFunction(int $count): Generator
{
    for ($i = 0; $i < $count; $i++) {
        yield $i;
    }
}

/**
 * Generates 10 million numbers using an array (Traditional Loop).
 */
function loopFunction(int $count): array
{
    $numbers = [];
    for ($i = 0; $i < $count; $i++) {
        $numbers[] = $i;
    }

    return $numbers;
}

// Define dataset size
$size = 10000000;

// Test Generator (yield)
$startTime = microtime(true);
$startMemory = memory_get_usage();
$total_gen_iteration = 0;
foreach (generatorFunction($size) as $num) {
    // Just iterating
    $total_gen_iteration++;
}
$endMemory = memory_get_usage();
$endTime = microtime(true);

$generatorTime = $endTime - $startTime;
$generatorMemory = memory_get_peak_usage() - $startMemory;

// Test Traditional Loop (Array)
$startTime = microtime(true);
$startMemory = memory_get_usage();
$numbers = loopFunction($size);
$total_loop_iteration = 0;
foreach ($numbers as $num) {
    // Just iterating
    $total_loop_iteration++;
}
$endMemory = memory_get_usage();
$endTime = microtime(true);

$loopTime = $endTime - $startTime;
$loopMemory = memory_get_peak_usage() - $startMemory;

// Display Results
echo "Performance Comparison for 10 Million Data Points\n";
echo "------------------------------------------------\n";
echo "Using Generator (yield): $total_gen_iteration \n";
echo 'Execution Time: '.number_format($generatorTime, 4)." sec\n";
echo 'Memory Usage: '.number_format($generatorMemory / (1024 * 1024), 4)." MB\n";
echo "------------------------------------------------\n";
echo "Using Traditional Loop (Array): $total_loop_iteration \n";
echo 'Execution Time: '.number_format($loopTime, 4)." sec\n";
echo 'Memory Usage: '.number_format($loopMemory / (1024 * 1024), 4)." MB\n";
