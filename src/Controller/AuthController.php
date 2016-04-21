<?php
/**
 * Created by PhpStorm.
 * User: manus
 * Date: 13/1/16
 * Time: 19:28
 */

namespace GLFramework\Controller;


use GLFramework\Controller;
use GLFramework\Middleware;
use GLFramework\Model\User;
use GLFramework\Request;
use GLFramework\Response;

class AuthController extends Controller implements Middleware
{

    private $session_key = "auth_user";
    /**
     * @var User
     */
    var $user;
    private $requireLogin = true;

    public function __construct($base, $module)
    {
        parent::__construct($base, $module);
        if(isset($_SESSION[$this->session_key]))
        {
            $username =  $_SESSION[$this->session_key][0];
            $password =  $_SESSION[$this->session_key][1];
            $user = $this->instanceUser(null);
            $user = $user->getByUserPassword($username, $password);
            if($user)
            {
                $this->user = $this->instanceUser($user);
            }
            else{
                unset($_SESSION[$this->session_key]);
            }
        }
        $this->addMiddleware($this);
    }


    public function login()
    {
        // TODO: Implement run() method.

        if(isset($_GET['logout']))
        {
            $this->addMessage("Se ha desconectado correctamente");
            unset($_SESSION[$this->session_key]);
        }
        if($this->requireLogin)
        {
            if(!isset($_SESSION[$this->session_key]))
            {
                if(strpos($_SERVER['REQUEST_URI'], "/login") === FALSE)
                {
                    if(!isset($_GET['logout']))
                        $this->addMessage("Por favor acceda con su cuenta antes de continuar", "warning");
                    $this->quit($this->config['app']['basepath'] . "/login");
                    return false;
                }
            }
        }
        return true;
    }

    public function processLogin($username = null, $password = null, $encrypt = true)
    {
        if($username === null)
            $username = $_POST['username'];
        if($password === null)
            $password = $_POST['password'];
        if(isset($username) && isset($password))
        {
            $this->csrf();
            $user = $this->instanceUser(null);
            $db = $this->getDb();
            $username = $db->escape_string($username); // Para evitar inyecciones SQL
            if($encrypt)
                $password = $user->encrypt($password);
            $user = $user->getByUserPassword($username, $password);
            if($user)
            {
                $this->user = new User($user);
                $_SESSION[$this->session_key] = array($username, $password);
                $this->redirection("/home");
                return true;
            }
            else{
                $this->addMessage("Usuario o contraseña incorrecta", "danger");
            }
        }
        return false;
    }

    /**
     * @return boolean
     */
    public function isRequireLogin()
    {
        return $this->requireLogin;
    }

    /**
     * @param boolean $requireLogin
     */
    public function setRequireLogin($requireLogin)
    {
        $this->requireLogin = $requireLogin;
    }

    /**
     * @param $data
     * @return User
     */
    public function instanceUser($data = null)
    {
        if(class_exists("User"))
            return new \User($data);
        return new User($data);
    }

    public function next(Request $request, Response $response, $next)
    {
        if($this->login())
        {
            $next($request, $response);
        }
        // TODO: Implement next() method.
    }

    public function run()
    {
        // Por motivos de compativilidad.
        // Lo ideal esque esta calse sea abstracta
    }
}