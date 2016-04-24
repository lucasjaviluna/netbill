<?php

namespace App\Commons;

use Illuminate\Support\Facades\Config as Config;
use Illuminate\Support\Facades\DB as DB;

/*
 
$otf = new Multidb(['database' => 'pruebita']);

// Get the users table
$users = $otf->getTable('usuario');

// Find the first user in the table
$first_user = $users->first();

 $otf = new Multidb([
    'driver'   => 'pgsql',
    'database' => 'puppies',
    'username' => 'jack',
    'password' => 'the-cute-dog',
]);

App\
 */

class Multidb {

    /**
     * The name of the database we're connecting to on the fly.
     *
     * @var string $database
     */
    protected $database;

    /**
     * The on the fly database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * Create a new on the fly database connection.
     *
     * @param  array $params
     * @return void
     */
    public function __construct($params = null) {
        // Set the database
        if (!isset($params['database'])) {
            throw new Exception('No se especifico database a la que conectarse');
        }
        $database = $params['database'];
        $this->database = $database;
        // Figure out the driver and get the default configuration for the driver
        $driver = isset($params['driver']) ? $params['driver'] : Config::get("database.default");
        $default = Config::get("database.connections.$driver");

        // Loop through our default array and update options if we have non-defaults
        foreach ($default as $item => $value) {
            $default[$item] = isset($params[$item]) ? $params[$item] : $default[$item];
        }

        // Set the temporary configuration
        Config::set("database.connections.$database", $default);
        // Create the connection
        $this->connection = DB::connection($database);
    }

    /**
     * Get the on the fly connection.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Get a table from the on the fly connection.
     *
     * @var    string $table
     * @return \Illuminate\Database\Query\Builder
     */
    public function getTable($table = null) {
        return $this->getConnection()->table($table);
    }
}