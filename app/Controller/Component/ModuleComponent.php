<?php

class ModuleComponent extends Object {

	protected $controller;

	function shutdown(&$controller) {}
	function beforeRender(&$controller) {}
  	function beforeRedirect() {}
	function initialize(&$controller) {
		$this->controller =& $controller;
		$this->controller->set('Module', new ModuleComponent());
	}
    function startup(&$controller) {}

    private function loadPlugins() { // on donne une liste des plugins
    	App::import('Component', 'EyPluginComponent'); // on charge le composant des plugins
    	$this->EyPlugin = new EyPluginComponent;
    	return $this->EyPlugin->get_list(); // et on retourne la liste
    }

    private function listModules() { // on fais une liste des modules disponibles parmis les plugins
    	$plugins = $this->loadPlugins();
    	foreach ($plugins as $key => $value) {
    		$folder = @scandir(ROOT.'/app/Plugin/'.$value['plugins']['name'].'/Modules');
    		if($folder) {
			    $folder = array_delete_value($folder, '.');
			    $folder = array_delete_value($folder, '..');
			    $folder = array_delete_value($folder, '.DS_Store');
			    foreach ($folder as $k => $v) {
			    	$modules[explode('.', $v)[0]][] = $value['plugins']['name'];
			    }
			}
    	}
    	if(!empty($modules)) {
    		return $modules;
    	} else {
    		return array();
    	}
    }

    public function loadModules($name) { // affiche le model demandé 
    	$HTML = '';
    	$list = $this->listModules();
    	if(isset($list[$name])) {
    		foreach ($list as $key => $value) {
    			foreach ($value as $k => $v) {
    				ob_start();
					include ROOT.'/app/Plugin/'.$v.'/Modules/'.$key.'.ctp';
    				$HTML = $HTML."\n".ob_get_clean();
    			}
    		}
    		return $HTML;
    	} else {
    		return false;
    	}
    }

}