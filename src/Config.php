<?php namespace Robinncode\DbCraft;

class Config
{
    public const CURRENT_VERSION = '1.0.0';

    public function __construct()
    {
        date_default_timezone_set('Asia/Dhaka');
        defined('CURRENT_TIME') || define("CURRENT_TIME", date('H:i:s'));
        defined('CURRENT_DATE') || define("CURRENT_DATE", date('Y-m-d'));
        defined('CURRENT_DATETIME') || define("CURRENT_DATETIME", date('Y-m-d H:i:s'));
        defined('PRETTIFY_DATETIME') || define("PRETTIFY_DATETIME", date('d F, Y h:i:s A'));
        defined('MIGRATION_FILE_TIMESTAMP') || define("MIGRATION_FILE_TIMESTAMP", date('Y_m_d_His'));
    }
}
