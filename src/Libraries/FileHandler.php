<?php namespace Robinncode\DbCraft\Libraries;

use CodeIgniter\CLI\CLI;
use Config\Services;

class FileHandler
{
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
}