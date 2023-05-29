# DB-Craft

DB-Craft is a package for CodeIgniter 4 that provides convenient commands to generate migration and seeder files from a connected database.

## Installation

You can install the DB-Craft package via Composer by running the following command:

```
composer require robinncode/db_craft
```
## Dependencies

DB-Craft has the following dependencies:

- PHP: ^7.0.* || ^8.0.*
- CodeIgniter/Framework: ^4.0.*


## Usage

DB-Craft comes with the following commands:

### Generate Migration Files

To generate all migration files from the connected database, run the following command:

```
php spark get:migration
```

To generate migration files for a specific table, run the following command:

```
php spark get:migration table_name
```

Replace `table_name` with the name of the specific table for which you want to generate migration files.

### Generate Seeder Files

To generate all seeder files from the connected database, run the following command:

```
php spark get:seed
```

To generate seeder files for a specific table with table data, run the following command:

```
php spark get:seed table_name
```

Replace `table_name` with the name of the specific table for which you want to generate seeder files.

## License

DB-Craft is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

