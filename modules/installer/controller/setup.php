<?php

namespace Core\Installer;
use GLFramework\Controller;
use GLFramework\DatabaseManager;
use GLFramework\Events;
use GLFramework\Model\User;
use GLFramework\Module\ModuleManager;
use Symfony\Component\Yaml\Yaml;

/**
 * Created by PhpStorm.
 * User: manus
 * Date: 26/5/16
 * Time: 23:10
 */
class setup extends Controller
{

    var $yamlDestination = "autogenerated.config.yml";
    var $step;
    var $db_config;
    var $hasAdmin;
    var $steps = array();
    var $view = "steps/1.twig";

    /**
     * Implementar aqui el código que ejecutara nuestra aplicación
     * @return mixed
     */
    public function run()
    {
        // TODO: Implement run() method.
        if($this->config['app']['configured'])
        {
            $this->addMessage("El sitio ya esta configurado!", "warning");
            $this->quit("/");
            return true;
        }
        $this->step = $this->params['step'];
        $this->steps = $this->getSteps();
        if(!$this->step) $this->step = "1";
        $index = intval($this->step) - 1;
        if(!isset($this->steps[$index]))
        {
            $this->addMessage("Este paso no esta disponible en el instalador!");
            return $this->quit($this->getLink($this, array('step' => '1')));
        }
        $call = $this->steps[$index]['function'];
        if(($view = call_user_func($call, $this)) === true)
        {
            return $this->quit($this->getLink($this, array('step' => $index + 2)));
        }
        $this->view = $view;
    }

    private function step1()
    {
        if(isset($_POST['save']))
        {
            $config = $this->loadCurrentConfig();
            $config['app']['name'] = $_POST['site_name'];
            $config['app']['debug'] = $_POST['debug']?true:false;
            if($this->saveConfig($config))
            {
                return true;
            }
        }
        return "steps/1.twig";
    }

    private function step2()
    {
        if(isset($_POST['save']) || isset($_POST['create_database']))
        {
            $config = $this->loadCurrentConfig();
            $config['database']['hostname'] = $_POST['hostname'];
            $config['database']['username'] = $_POST['username'];
            $config['database']['password'] = $_POST['password'];
            $config['database']['database'] = $_POST['database'];
            try
            {
                $this->db_config = new DatabaseManager($config);
                if(isset($_POST['create_database']))
                {
                    if($this->db_config->exec("CREATE DATABASE `{$config['database']['database']}`"))
                    {
                        $this->db_config->reset();
                        if($this->db_config->connect())
                        {
                            $this->addMessage("Se ha creado la base de datos con éxito");
                        }
                    }
                    else
                    {
                        $this->addMessage("No se ha podido crear la base de datos", "danger");
                    }
                }

                if($this->db_config->isSelected())
                {
                    $this->addMessage("Se ha conectado correctamente con la base de datos");
                    $this->saveConfig($config);

                    return true;
                }
                else
                {
                    $this->addMessage("No se puede econtrar la base de datos", "danger");
                }

            }
            catch (\Exception $ex)
            {
                Events::fire('onException', $ex);
                $this->addMessage($ex->getMessage(), "danger");
            }
        }
        return "steps/2.twig";
    }

    private function step3()
    {
        if(isset($_POST['save']))
        {
            return true;
        }
        return "steps/3.twig";
    }

    private function step4()
    {
        $user = \User::newInstance('User'); // Allow Model Override
        $this->hasAdmin = $user->get(array('admin' => '1'))->count() > 0;
        if(!$this->hasAdmin)
        {
            if(isset($_POST['save']))
            {
                if($_POST['password'] == $_POST['password_retype'])
                {
                    $user->admin = 1;
                    $user->user_name = $_POST['username'];
                    $user->nombre = $_POST['nombre'];
                    $user->password = $user->encrypt($_POST['password']);
                    $user->email = $_POST['email'];
                    if($user->save())
                    {
                        $this->addMessage("Se ha creado la cuenta correctamente. Login: " . $user->user_name);
                        return true;
                    }
                    else
                    {
                        $this->addMessage("Se ha producido un error al crear la cuenta", "danger");
                    }
                }
                else
                {
                    $this->addMessage("Las contraseñas no coinciden", "danger");
                }

            }
        }
        return "steps/4.twig";
    }

    private function step5()
    {
        if(isset($_POST['save']))
        {
            $config = $this->loadCurrentConfig();
            $config['app']['configured'] = true;
            $this->saveConfig($config);
            $this->quit("/");
        }
        return "steps/5.twig";
    }
    
    public function next()
    {
        return $this->getLink($this, array('step' => $this->step + 1));
    }

    public function loadCurrentConfig()
    {
        return Yaml::parse(file_get_contents($this->yamlDestination));
    }

    public function saveConfig($config)
    {
        $file = realpath(".") . "/" . $this->yamlDestination;
        if(!file_put_contents($file, Yaml::dump($config)))
        {
            $this->addMessage("No se puede escribir sobre '{$file}'", "danger");
            return false;
        }
        return true;
    }

    public function getSteps()
    {
        $steps = array();
        $steps[] = array(
            'title' => 'Configuracion Inicial',
            'function' => array($this, 'step1')
        );
        $steps[] = array(
            'title' => 'Configuracion Base de datos',
            'function' => array($this, 'step2')
        );
        $steps[] = array(
            'title' => 'Configuracion Correo',
            'function' => array($this, 'step3')
        );
        $steps[] = array(
            'title' => 'Cuenta Administrador',
            'function' => array($this, 'step4')
        );
        foreach ($this->getInstallers() as $installer)
        {
            $instance = new $installer();

            $steps[] = array(
                'title' => $instance->name,
                'function' => array($instance, 'install')
            );
        }

        $steps[] = array(
            'title' => 'Finalizar configuracion',
            'function' => array($this, 'step5')
        );

        return $steps;
    }

    public function getInstallers()
    {
        $result = array();
        $items = Events::fire('getInstallersControllers');
        foreach ($items as $item)
        {
            if(!is_array($item)) $item = array($item);
            foreach ($item as $controller)
            {
                $result[] = $controller;
            }
        }
        return $result;
    }
}