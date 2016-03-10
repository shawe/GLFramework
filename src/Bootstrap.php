<?php
/**
 * Created by PhpStorm.
 * User: manus
 * Date: 13/1/16
 * Time: 17:34
 */

namespace GLFramework;


use GLFramework\Controller\ErrorController;
use GLFramework\Controller\ExceptionController;
use GLFramework\Module\ModuleManager;
use Symfony\Component\Yaml\Yaml;

class Bootstrap
{
    private static $singelton;
    /**
     * @var ModuleManager
     */
    private $manager;
    private $events;
    private $config;
    private $directory;

    /**
     * Bootstrap constructor.
     */
    public function __construct($directory)
    {
        $this->events = new Events();
        $this->directory = $directory;
        $this->init();
        self::$singelton = $this;
    }

    public static function getSingleton()
    {
        return self::$singelton;
    }


    public static function start($directory)
    {
        $bootstrap = new Bootstrap($directory);
        $bootstrap->run();
    }

    public function init()
    {
        $this->register_error_handler();
        date_default_timezone_set('Europe/Berlin');

        $this->config = Yaml::parse(file_get_contents($this->directory . "/config.yml"));
        $this->manager = new ModuleManager($this->config, $this->directory);
        $this->manager->init();
    }

    public function run()
    {
        session_start();
        $this->manager->run();
    }


    public function install()
    {
        echo "<pre>";
        $db = new DatabaseManager();
        if ($db->connect()) {
            $this->log("Connection to database ok");

            $this->log("Installing Database...");
            $models = $this->getModels();
            foreach ($models as $model) {
                $instance = new $model(null);
                if ($instance instanceof Model) {
                    $diff = $instance->getStructureDifferences();
                    $this->log("Installing table '" . $instance->getTableName() . "' generated by " . get_class($instance) . "...", 2);

                    foreach ($diff as $action) {
                        $this->log("Action: " . $action['sql'] . "...", 3, false);

                        if (isset($_GET['exec']))
                        {
                            try{

                                $db->exec($action['sql']);
                                $this->log("[OK]", 0);
                            }
                            catch(\Exception $ex)
                            {

                                $this->log("[FAIL]", 0);
                            }
                        }
                        echo "\n";
                    }
                }
            }
            if (!isset($_GET['exec'])) {
                $this->log("Please <a href='?exec'>click here</a> to make this changes in the database.");

            } else {

                $this->log("All done site ready for develop/production!");
            }
        } else {
            if ($db->getConnection() != null) {
                if (isset($_GET['create_database'])) {
                    if ($db->exec("CREATE DATABASE " . $this->config['database']['database'])) {
                        echo "Database created successful! Please reload the navigator";
                    } else {
                        echo "Can not create the database!";
                    }
                } else {
                    echo "Can not select the database <a href='install.php?create_database'>Try to create database</a>";
                }

            } else {

                echo "Cannot connect to database";
            }
        }
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return mixed
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @return ModuleManager
     */
    public function getManager()
    {
        return $this->manager;
    }



    public function getModels()
    {
        $list = array();
        foreach($this->getManager()->getModules() as $module)
        {
            foreach($module->getModels() as $model)
            {
                $list[] = $model;
            }
        }
        $files = scandir(__DIR__ . "/Model");
        foreach($files as $file)
        {
            if($file == "." || $file == "..") continue;
            $list[] = "GLFramework\\Model\\" . substr($file, 0, -4);
        }
        return $list;
    }


    public function log($message, $level = 1, $nl = true)
    {
        echo str_repeat("-", $level) . "> " . $message . ($nl?"\n":"");
    }


    function fatal_handler()
    {
        $errfile = "unknown file";
        $errstr = "shutdown";
        $errno = E_CORE_ERROR;
        $errline = 0;

        $error = \error_get_last();
//        error_clear_last();
        if ($error !== NULL) {
            $errno = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr = $error["message"];
            if(isset($this->config['app']['debug']) && $this->config['app']['debug'])
                ($this->format_error($errno, $errstr, $errfile, $errline));
        }

    }

    private function register_error_handler()
    {
        register_shutdown_function(array($this, "fatal_handler"));
    }

    function format_error($errno, $errstr, $errfile, $errline)
    {
//        $trace = print_r(debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true);

        echo "
  <table>
  <thead><th>Item</th><th>Description</th></thead>
  <tbody>
  <tr>
    <th>Error</th>
    <td><pre>$errstr</pre></td>
  </tr>
  <tr>
    <th>Errno</th>
    <td><pre>$errno</pre></td>
  </tr>
  <tr>
    <th>File</th>
    <td>$errfile</td>
  </tr>
  <tr>
    <th>Line</th>
    <td>$errline</td>
  </tr>
  <tr>
    <th>Trace</th>
    <td><pre>";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        echo "</pre></td>
  </tr>
  </tbody>
  </table>";

//        return $content;
    }


}