<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UV-ASSIST DATABASE CONFIGURATION
|--------------------------------------------------------------------------
|
| University of the Visayas
| Intelligent Campus Helpdesk Chatbot
|
| Database: MySQL
| Connection: PDO
|
*/


/*
|--------------------------------------------------------------------------
| DATABASE SETTINGS
|--------------------------------------------------------------------------
|
| LOCAL DEVELOPMENT
|
| Usually:
|   host     = localhost
|   port     = 3306
|
| XAMPP may use:
|   host     = 127.0.0.1
|   port     = 3306
|
| If your MySQL server uses another port, change DB_PORT.
|
*/

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u724192156_uvassist');
define('DB_USER', 'root');
define('DB_PASS', 'Testing123!');


/*
|--------------------------------------------------------------------------
| PDO CONNECTION
|--------------------------------------------------------------------------
*/

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];


try {

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION ERROR
    |--------------------------------------------------------------------------
    |
    | Never expose the actual database username, password, host, or
    | SQL connection details to users in production.
    |
    */

    error_log(
        'UV-ASSIST Database Connection Error: '
            . $e->getMessage()
    );

    http_response_code(500);

    die('Database connection failed.');
}
