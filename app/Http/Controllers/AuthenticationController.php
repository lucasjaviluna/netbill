<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\RegisterRequest;
use App\Commons\Multidb;
use Illuminate\Support\Facades\Config as Config;

use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AuthenticationController extends Controller
{
    public function index(Request $request) {
        var_dump($request->all());
        die();
    }

    public function store(Request $request) {
        
    }

    public function login(Request $request) {
        if (Auth::attempt(['email' => $request['email'], 'password' => $request['password']])) {
            return Redirect::to('dashboard');
        }

        dd('No login');
    }

    public function logout() {
        Auth::logout();
        return Redirect::to('/');
    }

    public function register(RegisterRequest $request) {
        //throw new \Exception('No se puedo ejecutar la query: ');
        try
        {
            $email = $request['email'];
            Log::info(sprintf('Creando usuario %s', $email));
            User::create($request->all());

            //recover user
            $user = DB::table('users')->where('email', $email)->first();
            $userId = $user->id;
            $dbname = sprintf(env('CLIENT_DB_NAME', 'netbill_%d'), $userId);
            $username = sprintf('%d_client', $userId);
            $password = sprintf('%d_%d' , $userId, time());

            $pdo = DB::connection('mysql')->getPdo();
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $pdo->exec("CREATE DATABASE $dbname");
            $pdo->exec("USE $dbname");

            $dumpFile = config('database.commons.masterSQLDump');
            if(empty($dumpFile) || !file_exists($dumpFile) || !is_readable($dumpFile))
            {
                //escribir error en flash session
                dd('No se encuentra el fichero dump para la creacion de DB de administradores: '.$dumpFile);
            }
            $dump = file_get_contents($dumpFile);
            $arrayTables = explode(";\n", $dump);

            try
            {
                foreach ($arrayTables as $table)
                {
                    $table = trim($table);
                    if(!empty($table))
                    {
                        $ret = $pdo->exec($table);
                    }
                }
            }
            catch(Exception $e)
            {
                throw new \Exception('No se puedo ejecutar la query: '.$table);
            }

            $default = Config::get("database.connections.mysql");
            $host = $default['host'];
            //$ret = $pdo->query(sprintf('GRANT CREATE,DROP,SELECT,INSERT,UPDATE,DELETE,LOCK TABLES,CREATE VIEW,CREATE TEMPORARY TABLES ON %s.* TO "%s"@"%%" IDENTIFIED BY "%s";',$dbname, $username, $password));
            $ret = $pdo->query(sprintf('GRANT CREATE,DROP,SELECT,INSERT,UPDATE,DELETE,LOCK TABLES,CREATE VIEW,CREATE TEMPORARY TABLES ON %s.* TO "%s"@"%s" IDENTIFIED BY "%s";',$dbname, $username, $host, $password));
            $pdo = null;

            $userCx = new Multidb(['database' => 'clients']);
            $userUpdated = $userCx->getConnection()
                ->update('update users set db_user = :db_user, db_password = :db_password, db_host = :db_host where id = :id',
                ['db_user' => $username, 'db_password' => $password, 'db_host' => $host, 'id' => $userId]);

            $userCx->getConnection()->disconnect();

            if($userUpdated) {
                Log::info(sprintf('Usuario creado %d - %s', $userId, $email));
                $urlLogin = url('login');
                Session::flash('message-register', "Registro completo! <a href='$urlLogin'>Ahora puedes iniciar sesión</a>");
                return Redirect::to('/register');
            } else {
                throw new \Exception(sprintf('Error al crear usuario [%s]', $email));
            }
        }
        catch (Exception $ex)
        {
            Log::error($ex->getMessage());
            throw new \Exception('No se puede crear la Base de datos: '.$dbname."\n".$ex->getMessage());
        }
    }
}
