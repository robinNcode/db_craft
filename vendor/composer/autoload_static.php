<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite82b8245945cb29db431af3b6cd28e27
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'Robinncode\\DbCraft\\' => 19,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Robinncode\\DbCraft\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Robinncode\\DbCraft\\Commands\\GetMigrationCommand' => __DIR__ . '/../..' . '/src/Commands/GetMigrationCommand.php',
        'Robinncode\\DbCraft\\Commands\\GetSeedCommand' => __DIR__ . '/../..' . '/src/Commands/GetSeedCommand.php',
        'Robinncode\\DbCraft\\Config' => __DIR__ . '/../..' . '/src/Config.php',
        'Robinncode\\DbCraft\\Libraries\\FileHandler' => __DIR__ . '/../..' . '/src/Libraries/FileHandler.php',
        'Robinncode\\DbCraft\\Libraries\\MigrationGenerator' => __DIR__ . '/../..' . '/src/Libraries/MigrationGenerator.php',
        'Robinncode\\DbCraft\\Libraries\\SeederGenerator' => __DIR__ . '/../..' . '/src/Libraries/SeederGenerator.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite82b8245945cb29db431af3b6cd28e27::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite82b8245945cb29db431af3b6cd28e27::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite82b8245945cb29db431af3b6cd28e27::$classMap;

        }, null, ClassLoader::class);
    }
}
