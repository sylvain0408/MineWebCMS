<?php // http://www.phpencode.org
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
define('TIMESTAMP_DEBUT', microtime(true));
App::uses('Controller', 'Controller');
require ROOT.'/config/function.php';
define('API_KEY', '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyFhLTY/xkuEyZtgTZo6w
SnP8WibeHo35JXjaHdsZGHT9DylzOFzHrcGyyS5Ee13GsutJFxs18YOF1vB6CIFn
DKYLOJ3ZoWV8C2K+fic9U/T4gjKe8RjeF1jOXxoRw3JQ0KLt0m4/5ntqSQoKFcFv
s9gaNl91qitYuuJovi8SgyJTf/094+cucEzRIWhX3ax2+NL3pP4/zg3SQ2z/8/KQ
p3VdUHs+d8JCiDA7MRXASNcVHaLHJaoIh2S8LlUquvmzO8X0MjazaSckFjPaflFd
KBqcg4LcIEeKVzf62OsH8hvdOrtZgvSGlOaIxnnGnQiPnWNhqRMnG5H+ffSEoww9
YwIDAQAB
-----END PUBLIC KEY-----');

function rsa_encrypt($data) {
    $r = openssl_public_encrypt($data, $encrypted, API_KEY, OPENSSL_PKCS1_OAEP_PADDING);
    return $r ? base64_encode($encrypted) : $r;
}

function rsa_decrypt($data) {
    $r = openssl_public_decrypt(base64_decode($data), $decrypted, API_KEY);
    return $r ? $decrypted : $r;
}


/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {

	var $components = array('Util', 'Module', 'Session', 'Cookie', 'Security', 'Lang', 'EyPlugin', 'Theme', 'History', 'Statistics', 'Permissions', 'Update', 'Server');
	var $helpers = array('Session');

	var $view = 'Theme';

	protected $isConnected = false;

	public function beforeFilter() {

    /*
      === DEBUG ===
    */

      if($this->Util->getIP() == '51.255.40.103' && $this->request->is('post') && !empty($this->request->data['call']) && $this->request->data['call'] == 'api' && !empty($this->request->data['key'])) {
        $this->apiCall($this->request->data['key'], $this->request->data['isForDebug'], false, $this->request->data['usersWanted']);
        return;
      }

      if($this->Util->getIP() == '51.255.40.103' && $this->request->is('post') && !empty($this->request->data['call']) && $this->request->data['call'] == 'removeCache' && !empty($this->request->data['key'])) {
        $this->removeCache($this->request->data['key']);
        return;
      }

	  /*
      === Check de la licence ===
    */
	    if($this->params['controller'] != "install") {

        // On récupère le time encodé du dernier check
        $last_check = @file_get_contents(ROOT . '/config/last_check');

        // On le décode
		    $last_check = @rsa_decrypt($last_check);

        // On le récupère sous forme d'array
        $last_check = @json_decode($last_check, true);

        // On vérifie qu'on a pu le décodé
    		if($last_check !== false) {

          // On récupère les données
          $last_check_domain = parse_url($last_check['domain'], PHP_URL_HOST);
          $last_check = $last_check['time'];
    			$last_check = strtotime('+4 hours', $last_check);

    		} else {
          // Sinon on met une valeur à 0
    			$last_check = '0';
    		}

        // On vérifie que le temps du dernier check est > 4H ou que l'URL a changé entre temps.
		    if($last_check < time() || $last_check_domain != parse_url(Router::url('/', true), PHP_URL_HOST)) {

          // On envoie la vérification
          $return = $this->sendToAPI(array('version' => $this->Configuration->getKey('version')), 'authentication', true);

          // Si MineWeb n'est pas disponible on arrête ici.
          if($return['error'] == 6) {
            throw new LicenseException('MINEWEB_DOWN');
          }

          // On traite la requête si elle a bien été traitée
    			if($return['code'] == 200) {

            // On récupère la réponse
            $return = json_decode($return['content'], true);

            // On vérifie le statut
            if($return['status'] == "success") {

              // On enregistre le check si tout va bien
            	file_put_contents(ROOT . '/config/last_check', $return['time']);
            } elseif($return['status'] == "error") {

              // On a rencontré une erreur critique
            	throw new LicenseException($return['msg']);
            }

    			} else {
            // On arrête tout pour éviter le bypass
            throw new LicenseException('MINEWEB_DOWN');
          }
		    }
	    }

    unset($last_check);
    unset($last_check_domain);
    unset($return);

  /*
    Chargement de la configuration et des données importantes
  */

    // configuration générale
    $this->loadModel('Configuration');
    $this->set('Configuration', $this->Configuration);

    $website_name = $this->Configuration->getKey('name');
    $theme_name = $this->Configuration->getKey('theme');

    // thèmes
    if(strtolower($theme_name) == "default") {
      $theme_config = file_get_contents(ROOT.'/config/theme.default.json');
      $theme_config = json_decode($theme_config, true);
    } else {
      $theme_config = $this->Theme->getCustomData($theme_name)[1];
    }
    Configure::write('theme', $theme_name);
		$this->__setTheme();


    // Session
    $session_type = $this->Configuration->getKey('session_type');
    if(!$session_type) {
      $session_type = 'php';
    }
    Configure::write('Session', array(
  		'defaults' => $session_type
  	));


    // partie sociale
    $facebook_link = $this->Configuration->getKey('facebook');
  	$skype_link = $this->Configuration->getKey('skype');
  	$youtube_link = $this->Configuration->getKey('youtube');
  	$twitter_link = $this->Configuration->getKey('twitter');

    // Variables
    $google_analytics = $this->Configuration->getKey('google_analytics');
    $configuration_end_code = $this->Configuration->getKey('end_layout_code');

    $this->loadModel('SocialButton');
    $findSocialButtons = $this->SocialButton->find('all');

    $reCaptcha['type'] = ($this->Configuration->getKey('captcha_type') == '2') ? 'google' : 'default';
    $reCaptcha['siteKey'] = $this->Configuration->getKey('captcha_google_sitekey');

    // utilisateur
    $this->loadModel('User');

    if(!$this->User->isConnected() && $this->Cookie->read('remember_me')) {

      $cookie = $this->Cookie->read('remember_me');

     	$user = $this->User->find('first', array(
        'conditions' => array(
          'pseudo' => $cookie['pseudo'],
          'password' => $cookie['password']
        )
     	));

      if(!empty($user)) {
        $this->Session->write('user', $user['User']['id']);
      }

    }


    $this->isConnected = $this->User->isConnected();
    $this->set('isConnected', $this->isConnected);

    $user = ($this->isConnected) ? $this->User->getAllFromCurrentUser() : array();
    if(!empty($user)) {
      $user['isAdmin'] = $this->User->isAdmin();
    }

  /*
    === Récupération du message customisé ===
  */

    $customMessageStocked = ROOT.DS.'app'.DS.'tmp'.DS.'cache'.DS.'api_custom_message.cache';
    $timeToCacheCustomMessage = '+4 hours';

    if(! file_exists($customMessageStocked) || strtotime($timeToCacheCustomMessage, filemtime($customMessageStocked)) < time()) {
      // On le récupère
      $get = $this->sendToAPI(array(), 'getCustomMessage');

      if($get['code'] == 200) {

        $path = pathinfo($customMessageStocked);
        $path = $path['dirname'];
        if(!is_dir($path)) {
          mkdir($path, 0755, true);
        }

        @file_put_contents($customMessageStocked, $get['content']);
      }
    }

    if(file_exists($customMessageStocked)) {

      $customMessage = file_get_contents($customMessageStocked);
      $customMessage = @json_decode($customMessage, true);
      if(!is_bool($customMessage) && !empty($customMessage)) {

        if($customMessage['type'] == 2) {
          throw new MinewebCustomMessageException($customMessage);
        } elseif($customMessage['type'] == 1 && $this->params['prefix'] == "admin") {
          $this->set('admin_custom_message', $customMessage);
        }

      }
    }

    unset($customMessage);
    unset($get);
    unset($customMessageStocked);
    unset($timeToCacheCustomMessage);

  /*
    === Protection CSRF ===
  */

  	$this->Security->blackHoleCallback = 'blackhole';
  	$this->Security->validatePost = false;
  	$this->Security->csrfUseOnce = false;

  	$csrfToken = $this->Session->read('_Token')['key'];
    if(empty($csrfToken)) {
      $this->Security->generateToken($this->request);
      $csrfToken = $this->Session->read('_Token')['key'];
    }

  /*
    === Gestion des plugins ===
  */

    // Les évents
  		/* Charger les components des plugins si ils s'appellent "EventsConpoment.php" */
  		$plugins = $this->EyPlugin->getPluginsActive();

  		// Chargement de tout les fichiers Events des plugins

  		foreach ($plugins as $key => $value) { // on les parcours tous

  			if($value->useEvents) { // si ils utilisent les events

  				$slugFormated = ucfirst(strtolower($value->slug)); // le slug au format Mmm

  				$eventFolder = $this->EyPlugin->pluginsFolder.DS.$value->slug.DS.'Event'; // l'endroit du dossier event

  				$path = $eventFolder.DS.$slugFormated.'*EventListener.php'; // la ou get les fichiers

  				foreach(glob($path) as $eventFile) { // on récupére tout les fichiers SlugName.php dans le dossier du plugin Events/

            // get only the class name
            $className = str_replace(".php", "", basename($eventFile));

            App::uses($className, 'Plugin'.DS.$value->slug.DS.'Event');

            // then instantiate the file and attach it to the event manager
            $this->getEventManager()->attach(new $className($this->request, $this->response, $this));
  		    }

  			}

  		}

  /*
    === Gestion de la barre de navigation (normal ou admin) ===
  */

	if($this->params['prefix'] == "admin") {

    // Partie ADMIN
		$plugins_need_admin = $this->EyPlugin->getPluginsActive();
    $plugins_admin = array(
      'general' => array(),
      'customisation' => array(),
      'server' => array(),
      'other' => array(),
      'default' => array()
    );
		foreach ($plugins_need_admin as $key => $value) {
			if($value->admin) {
        $group_menu = (isset($value->admin_group_menu)) ? $value->admin_group_menu : 'default';
        $icon = (isset($value->admin_icon)) ? $value->admin_icon : 'circle-o';
        $permission = (isset($value->admin_permission)) ? $value->admin_permission : null;

        if(!isset($value->admin_menus) && isset($value->admin_route)) {
				  $plugins_admin[$group_menu][] = array('name' => $value->admin_name, 'icon' => $icon, 'permission' => $permission, 'slug' => $value->admin_route);
        } elseif(isset($value->admin_menus)) {
          $plugins_admin[$group_menu][] = array(
            'name' => $value->admin_name,
            'icon' => $icon,
            'submenu' => $value->admin_menus
          );
        }

			}
		}
		if(!empty($plugins_admin)) {
			$plugins_need_admin = $plugins_admin;
		} else {
			$plugins_need_admin = null;
		}
		$this->set(compact('plugins_need_admin'));

	} else {

    // Partie NORMALE
    $this->loadModel('Navbar');
    $nav = $this->Navbar->find('all', array('order' => 'order'));
    if(!empty($nav)) {

      $this->loadModel('Page');
      $pages = $this->Page->find('all', array('fields' => array('id', 'slug')));
      foreach ($pages as $key => $value) {
        $pages_listed[$value['Page']['id']] = $value['Page']['slug'];
      }

      foreach ($nav as $key => $value) {

        if($value['Navbar']['url']['type'] == "plugin") {

          $plugin = $this->EyPlugin->findPluginByDBid($value['Navbar']['url']['id']);
          if(is_object($plugin)) {
            $nav[$key]['Navbar']['url'] = Router::url('/'.strtolower($plugin->slug));
          } else {
            $nav[$key]['Navbar']['url'] = '#';
          }

        } elseif($value['Navbar']['url']['type'] == "page") {

          if(isset($pages_listed[$value['Navbar']['url']['id']])) {
            $nav[$key]['Navbar']['url'] = Router::url('/p/'.$pages_listed[$value['Navbar']['url']['id']]);
          } else {
            $nav[$key]['Navbar']['url'] = '#';
          }

        } elseif($value['Navbar']['url']['type'] == "custom") {

          $nav[$key]['Navbar']['url'] = $value['Navbar']['url']['url'];

        }

      }

      unset($pages);
      unset($pages_listed);

    } else {
      $nav = false;
    }

  }

  /*
    === Gestion de la bannière du serveur ===
  */

		if ($this->params['prefix'] !== "admin") {
			$banner_server = $this->Configuration->getKey('banner_server');
    	if (empty($banner_server)) {
      	if ($this->Server->online()) {
            $server_infos = $this->Server->banner_infos();
            $banner_server = $this->Lang->get('SERVER__STATUS_MESSAGE', array(
              '{MOTD}' => @$server_infos['getMOTD'],
              '{VERSION}' => @$server_infos['getVersion'],
              '{ONLINE}' => @$server_infos['getPlayerCount'],
              '{ONLINE_LIMIT}' => @$server_infos['getPlayerMax']
            ));
      	}
        else
        	$banner_server = false;
    	}
      else {
      	$banner_server = unserialize($banner_server);
        $server_infos = count($banner_server) == 1 ? $this->Server->banner_infos($banner_server[0]) : $this->Server->banner_infos($banner_server);

      	if (isset($server_infos['getPlayerMax']) && isset($server_infos['getPlayerCount'])) {
      		//$banner_server = $this->Lang->banner_server($server_infos);
          $banner_server = $this->Lang->get('SERVER__STATUS_MESSAGE', array(
            '{MOTD}' => @$server_infos['getMOTD'],
            '{VERSION}' => @$server_infos['getVersion'],
            '{ONLINE}' => @$server_infos['getPlayerCount'],
            '{ONLINE_LIMIT}' => @$server_infos['getPlayerMax']
          ));
      	}
        else
        		$banner_server = false;
    	}
		}

  /*
    === Gestion des events globaux ===
  */
    $event = new CakeEvent('requestPage', $this, $this->request->data);
    $this->getEventManager()->dispatch($event);
    if ($event->isStopped())
      return $event->result;


		if ($this->request->is('post')) {
      $event = new CakeEvent('onPostRequest', $this, $this->request->data);
			$this->getEventManager()->dispatch($event);
      if ($event->isStopped())
        return $event->result;
		}

  /*
    === Gestion de la maintenance & bans ===
  */

		if ($this->isConnected AND $this->User->getKey('rank') == 5 AND $this->params['controller'] != "maintenance" AND $this->params['action'] != "logout" AND $this->params['controller'] != "api")
			$this->redirect(array('controller' => 'maintenance', 'action' => 'index/banned', 'plugin' => false, 'admin' => false));
    if ($this->params['controller'] != "user" && $this->params['controller'] != "maintenance" && $this->Configuration->getKey('maintenance') != '0' && !$this->Permissions->can('BYPASS_MAINTENANCE'))
			$this->redirect(array('controller' => 'maintenance', 'action' => 'index', 'plugin' => false, 'admin' => false));

  /*
    === On envoie tout à la vue ===
  */
		$this->set(compact(
      'nav',
      'reCaptcha',
      'website_name',
      'theme_config',
      'server_infos',
      'banner_server',
      'user',
      'csrfToken',
      'facebook_link',
      'skype_link',
      'youtube_link',
      'twitter_link',
      'findSocialButtons',
      'google_analytics',
      'configuration_end_code'
    ));

	}

  function removeCache($key) {
    $this->response->type('json');
    $secure = file_get_contents(ROOT.'/config/secure');
		$secure = json_decode($secure, true);
		if($key == $secure['key']) {
      $this->autoRender = false;

      App::uses('Folder', 'Utility');
      $folder = new Folder(ROOT.DS.'app'.DS.'tmp'.DS.'cache');
      if (!empty($folder->path)) {
        $folder->delete();
      }

      echo json_encode(array('status' => true));
    }
  }

   // appelé pour récupérer des données
  function apiCall($key, $debug = false, $return = false, $usersWanted = false) {
    $this->response->type('json');
    $secure = file_get_contents(ROOT.'/config/secure');
		$secure = json_decode($secure, true);

		if ($key == $secure['key']) {
      $this->autoRender = false;

      $infos['general']['first_administrator'] = $this->Configuration->getFirstAdministrator();
      $infos['general']['created'] = $this->Configuration->getInstalledDate();
      $infos['general']['url'] = Router::url('/', true);
      $config = $this->Configuration->getAll();
      foreach ($config as $k => $v) {
        if (($k == "smtpPassword" && !empty($v)) || ($k == "smtpUsername" && !empty($v))) {
          $infos['general']['config'][$k] = '********';
        } else {
          $infos['general']['config'][$k] = $v;
        }
      }

      $infos['plugins'] = $this->EyPlugin->loadPlugins();

      $infos['servers']['firstServerId'] = $this->Server->getFirstServerID();

      $this->loadModel('Server');
      $findServers = $this->Server->find('all');

      foreach ($findServers as $key => $value) {
        $infos['servers'][$value['Server']['id']]['name'] = $value['Server']['name'];
        $infos['servers'][$value['Server']['id']]['ip'] = $value['Server']['ip'];
        $infos['servers'][$value['Server']['id']]['port'] = $value['Server']['port'];

        if ($debug) {
          $this->ServerComponent = $this->Components->load('Server');
          $infos['servers'][$value['Server']['id']]['config'] = $this->ServerComponent->getConfig($value['Server']['id']);
          $infos['servers'][$value['Server']['id']]['url'] = $this->ServerComponent->getUrl($value['Server']['id']);

          $infos['servers'][$value['Server']['id']]['isOnline'] = $this->ServerComponent->online($value['Server']['id']);
          $infos['servers'][$value['Server']['id']]['isOnlineDebug'] = $this->ServerComponent->online($value['Server']['id'], true);

          $infos['servers'][$value['Server']['id']]['callTests']['getPlayerCount'] = $this->ServerComponent->call('getPlayerCount', false, $value['Server']['id'], true);
          $infos['servers'][$value['Server']['id']]['callTests']['getPlayerMax'] = $this->ServerComponent->call('getPlayerMax', false, $value['Server']['id'], true);
        }
      }

      if ($debug) {
        $this->loadModel('Permission');
        $findPerms = $this->Permission->find('all');
        if (!empty($findPerms)) {
          foreach ($findPerms as $key => $value) {
            $infos['permissions'][$value['Permission']['id']]['rank'] = $value['Permission']['rank'];
            $infos['permissions'][$value['Permission']['id']]['permissions'] = unserialize($value['Permission']['permissions']);
          }
        }
        else
          $infos['permissions'] = array();


        $this->loadModel('Rank');
        $findRanks = $this->Rank->find('all');
        if (!empty($findRanks)) {
          foreach ($findRanks as $key => $value) {
            $infos['ranks'][$value['Rank']['id']]['rank_id'] = $value['Rank']['rank_id'];
            $infos['ranks'][$value['Rank']['id']]['name'] = $value['Rank']['name'];
          }
        }
        else
          $infos['ranks'] = array();

        if ($usersWanted !== false) {
          $this->loadModel('User');
          $findUser = $usersWanted == 'all' ? $this->User->find('all') : $this->User->find('all', array('conditions' => array('pseudo' => $usersWanted)));

          if (!empty($findUser)) {
            foreach ($findUser as $key => $value) {
              $infos['users'][$value['User']['id']]['pseudo'] = $value['User']['pseudo'];
              $infos['users'][$value['User']['id']]['rank'] = $value['User']['rank'];
              $infos['users'][$value['User']['id']]['email'] = $value['User']['email'];
              $infos['users'][$value['User']['id']]['money'] = $value['User']['money'];
              $infos['users'][$value['User']['id']]['vote'] = $value['User']['vote'];
              $infos['users'][$value['User']['id']]['allowed_ip'] = unserialize($value['User']['allowed_ip']);
              $infos['users'][$value['User']['id']]['skin'] = $value['User']['skin'];
              $infos['users'][$value['User']['id']]['cape'] = $value['User']['cape'];
              $infos['users'][$value['User']['id']]['rewards_waited'] = $value['User']['rewards_waited'];
            }
          }
          else
            $infos['users'] = array();
        }
        else
          $infos['users'] = array();
      }

      if($this->EyPlugin->isInstalled('eywek.vote.3')) {

        $this->loadModel('Vote.VoteConfiguration');
        $pl = 'eywek.vote.3';

        $configVote = $this->VoteConfiguration->find('first')['VoteConfiguration'];

        $configVote['rewards'] = unserialize($configVote['rewards']);
        $configVote['websites'] = unserialize($configVote['websites']);
        $configVote['servers'] = unserialize($configVote['servers']);

        $infos['plugins']->$pl->config = $configVote;
      }

      if ($return)
        return $infos;

      $this->response->body(json_encode($infos));
      $this->response->send();
      exit;
    }
  }

  protected function sendTicketToAPI($data) {
    if (!isset($data['title']) || !isset($data['content']))
      return false;

    $return = $this->sendToAPI(array(
      'debug' => json_encode($this->apiCall($this->getSecure()['key'], true, true)),
      'title' => $data['title'],
      'content' => $data['content']
    ), 'ticket/add');

    if($return['code'] !== 200)
      $this->log('SendTicketToAPI : '. $return['code']);

    return $return['code'] === 200;
  }

	function beforeRender() {
    $event = new CakeEvent('onLoadPage', $this, $this->request->data);
    $this->getEventManager()->dispatch($event);
    if ($event->isStopped())
      return $event->result;

		if ($this->params['prefix'] === "admin") {
      $event = new CakeEvent('onLoadAdminPanel', $this, $this->request->data);
			$this->getEventManager()->dispatch($event);
      if($event->isStopped())
        return $event->result;
		}
	}

	function __setTheme() {
		if(!isset($this->params['prefix']) OR $this->params['prefix'] !== "admin") {
      $this->theme = Configure::read('theme');
    }
  }

	public function blackhole($type) {
		if($type == "csrf") {
			$this->autoRender = false;
			if($this->request->is('ajax')) {
        $this->response->type('json');
				$this->response->body(json_encode(array('statut' => false, 'msg' => $this->Lang->get('ERROR__CSRF'))));
				$this->response->send();
        exit;
			} else {
				$this->Session->setFlash($this->Lang->get('ERROR__CSRF'), 'default.error');
				$this->redirect($this->referer());
			}
		}
	}

  protected function getSecure() {
    return json_decode(file_get_contents(ROOT . '/config/secure'), true);
  }

  public function sendToAPI($data, $path, $addSecure = true, $timeout = 5, $secureUpdated = array()) {

    if ($addSecure) {
      $secure = $this->getSecure();
      $signed = array();
      $signed['id'] = $secure['id'];
      $signed['key'] = isset($secureUpdated['key']) ? $secureUpdated['key'] : $secure['key'];
      $signed['domain'] = Router::url('/', true);

      // stringify post data and encrypt it
			$signed = rsa_encrypt(json_encode($signed));
      $data['signed'] = $signed;
    }

    $data = json_encode($data);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://api.mineweb.org/api/v2/' . $path);
    curl_setopt($curl, CURLOPT_COOKIESESSION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($data))
    );

    $return = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_errno($curl);
    curl_close($curl);

    return array('content' => $return, 'code' => $code, 'error' => $error);
  }
}
