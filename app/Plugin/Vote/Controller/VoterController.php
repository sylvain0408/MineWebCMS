<?php
class VoterController extends VoteAppController {

	public $components = array('Configuration', 'Configuration');

    public function index() {
        $this->loadModel('VoteConfiguration');
        $search = $this->VoteConfiguration->find('all');
        if(!empty($search)) {
            //$id_vote = $search['0']['VoteConfiguration']['id_vote'];
            //$this->set(compact('id_vote'));

           /* $page_vote = $this->VoteConfiguration->find('all');
            $page_vote = $page_vote['0']['VoteConfiguration']['page_vote'];
            $page = file_get_contents($page_vote);

            $str = substr($page, strpos($page, 'Position'), 20);
            $position = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
            $this->set(compact('position'));

            $str = substr($page, strpos($page, 'Vote'), 20);
            $votes = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
            $this->set(compact('votes'));*/

            $this->set('vote_page', $search[0]['VoteConfiguration']['page_vote']);

            $rewards = $search[0]['VoteConfiguration']['rewards'];
            $rewards = unserialize($rewards);
            $this->set(compact('rewards'));

            $this->loadModel('User');
            $ranking = $this->User->find('all', array('limit' => '10', 'order' => 'vote desc'));
            $this->set(compact('ranking'));
        } else {
            throw new NotFoundException();
        }
    }

    public function ajax() {
    	$this->layout = null;
        $this->loadModel('User');
        $user_rank = $this->User->find('first', array('conditions' => array('pseudo' => $this->request->data['pseudo'])));
        if(!empty($user_rank) && $this->Permissions->have($user_rank['User']['rank'], 'VOTE') == "true") {
        	if($this->request->is('post')) {
        		if(!empty($this->request->data['step'])) {

        			if($this->request->data['step'] == 1) { // STEP 1

        				if(!empty($this->request->data['pseudo'])) { // si le pseudo n'est pas vide

        					if($this->Connect->user_exist($this->request->data['pseudo'])) {

    	    					$this->loadModel('Vote');
    	    					$get_last_vote = $this->Vote->find('all', array('conditions' => array('username' => $this->request->data['pseudo'])));
    	    					
    	    					if(!empty($get_last_vote['0']['Vote']['created'])) {
    	    						$now = time();
    	                			$last_vote = ($now - strtotime($get_last_vote['0']['Vote']['created']))/60;
    	                		} else {
    	                			$last_vote = null;
    	                		}

                                $this->loadModel('VoteConfiguration');
                                $time_vote = $this->VoteConfiguration->find('all');
                                $time_vote = $time_vote['0']['VoteConfiguration']['time_vote'];
    	    					if(empty($last_vote) OR $last_vote > $time_vote) {

    	    						echo $this->Lang->get('VOTE_LOGIN_SUCCESS').'|true';

    	    					} else {
    	    						echo $this->Lang->get('ALREADY_VOTED').'|false';
    	    					}
    	    				} else {
    	    					echo $this->Lang->get('UNKNOWN_USERNAME').'|false';
    	    				}

        				} else {
        					echo $this->Lang->get('COMPLETE_ALL_FIELDS').'|false';
        				}

        			/*} elseif($this->request->data['step'] == 3) { // STEP 3

        				if(!empty($this->request->data['out'])) { // si l'out n'est pas vide
                            $this->loadModel('VoteConfiguration');
                            $page_vote = $this->VoteConfiguration->find('all');
                            $page_vote = $page_vote['0']['VoteConfiguration']['page_vote'];
        					$page = file_get_contents($page_vote);
                    		$str = substr($page, strpos($page, 'Clic Sortant'), 20);
                    		$out = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
                            $array = array($out, $out-1, $out-2, $out-3, $out+1, $out+2, $out+3);

                    		if(in_array($this->request->data['out'], $array)) {

                    			echo $this->Lang->get('OUT_SUCCESS').'|true';

                    		} else {
        						echo $this->Lang->get('OUT_INVALID').'|false';
        					}

        				} else {
        					echo $this->Lang->get('COMPLETE_ALL_FIELDS').'|false';
        				}*/

        			} elseif($this->request->data['step'] == 4) { // STEP 4

                        if(/*!empty($this->request->data['out']) AND */!empty($this->request->data['pseudo'])) {

                            // je dois refaire toutes les vérifications

                            // il a le droit de voter ? il existe ? 
                            if($this->Connect->user_exist($this->request->data['pseudo'])) {

                                $this->loadModel('Vote');
                                $get_last_vote = $this->Vote->find('all', array('conditions' => array('username' => $this->request->data['pseudo'])));
                                
                                if(!empty($get_last_vote['0']['Vote']['created'])) {
                                    $now = time();
                                    $last_vote = ($now - strtotime($get_last_vote['0']['Vote']['created']))/60;
                                } else {
                                    $last_vote = null;
                                }

                                $this->loadModel('VoteConfiguration');
                                $time_vote = $this->VoteConfiguration->find('all');
                                $time_vote = $time_vote['0']['VoteConfiguration']['time_vote'];
                                if(empty($last_vote) OR $last_vote > $time_vote) {

                                    // il a le droit mais les out sont correct ? 
                                    $this->loadModel('VoteConfiguration');
                                    $page_vote = $this->VoteConfiguration->find('all');
                                    $page_vote = $page_vote['0']['VoteConfiguration']['page_vote'];
                                    /*$page = file_get_contents($page_vote);
                                    $str = substr($page, strpos($page, 'Clic Sortant'), 20);
                                    $out = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
                                    $array = array($out, $out-1, $out-2, $out-3, $out+1, $out+2, $out+3);*/
                            
                                    //if(in_array($this->request->data['out'], $array)) {

                                        $this->loadModel('Vote');
                                        $get_last_vote = $this->Vote->find('all', array('conditions' => array('username' => $this->request->data['pseudo'])));
                                        
                                        if(empty($get_last_vote)) {
                                            $this->Vote->read(null, null);
                                            $this->Vote->set(array(
                                                'username' => $this->request->data['pseudo'],
                                                'ip' => $_SERVER['REMOTE_ADDR']
                                            ));
                                            $this->Vote->save();
                                        } else {
                                            $this->Vote->read(null, $get_last_vote['0']['Vote']['id']);
                                            $this->Vote->set(array(
                                                'username' => $this->request->data['pseudo'],
                                                'ip' => $_SERVER['REMOTE_ADDR'],
                                                'created' => date('Y-m-d H:i:s')
                                            ));
                                            $this->Vote->save();
                                        }
                                        $this->loadModel('User');
                                        $user_id = $this->User->find('all', array('conditions' => array('pseudo' => $this->request->data['pseudo'])));
                                        $vote_nbr = $user_id[0]['User']['vote'] + 1;
                                        $this->User->read(null, $user_id['0']['User']['id']);
                                        $this->User->set(array(
                                            'vote' => $vote_nbr
                                        ));
                                        $this->User->save();

                                        $this->getEventManager()->dispatch(new CakeEvent('onVote', $this));

                                        // out valide alors on l'enregistre dans la bdd et fais la commande jsonapi
                                        $this->loadModel('VoteConfiguration');
                                        $config = $this->VoteConfiguration->find('all');
                                        $rewards_type = $config['0']['VoteConfiguration']['rewards_type']; // si le type de la récompense est 1 -> toutes les commandes sont effecutés, sinon si c'est 0 on fais une commande aléatoirement
                                        $rewards = $config['0']['VoteConfiguration']['rewards'];
                                        $rewards = unserialize($rewards);

                                        $this->getEventManager()->dispatch(new CakeEvent('beforeRecieveRewards', $this, $rewards));

                                        if($rewards_type == 0) { // on fais aléatoirement

                                            $random = rand(0, count($rewards)-1);

                                            if($rewards[$random]['type'] == 'server') { // si c'est une commande serveur

                                                $config['0']['VoteConfiguration']['servers'] = unserialize($config['0']['VoteConfiguration']['servers']);
                                                if(!empty($config['0']['VoteConfiguration']['servers'])) {
                                                    foreach ($config['0']['VoteConfiguration']['servers'] as $key => $value) {
                                                        $servers_online[] = $this->Server->online($value);
                                                    }
                                                } else {
                                                    $servers_online = array($this->Server->online());
                                                }
                                                if(!in_array(false, $servers_online)) {

                                                    if(empty($config['0']['VoteConfiguration']['servers'])) {
                                                        $cmd = str_replace('{PLAYER}', $this->request->data['pseudo'], $rewards[$random]['command']);
                                                        $this->Server->send_command($cmd); // on envoie la commande puis enregistre le vote
                                                        $msg = str_replace('{PLAYER}', $this->request->data['pseudo'], $this->Lang->get('VOTE_SUCCESS_SERVER'));
                                                        $this->Server->send_command('broadcast '.$msg);
                                                    } else {
                                                        foreach ($config['0']['VoteConfiguration']['servers'] as $key => $value) {
                                                            $cmd = str_replace('{PLAYER}', $this->request->data['pseudo'], $rewards[$random]['command']);
                                                            $this->Server->send_command($cmd, $value); // on envoie la commande puis enregistre le vote
                                                            $msg = str_replace('{PLAYER}', $this->request->data['pseudo'], $this->Lang->get('VOTE_SUCCESS_SERVER'));
                                                            $this->Server->send_command('broadcast '.$msg, $value);
                                                        }
                                                    }

                                                    echo $this->Lang->get('VOTE_SUCCESS').' '.$this->Lang->get('REWARD').' : <b>'.$rewards[$random]['name'].'</b>.|true';

                                                } else {
                                                    echo $this->Lang->get('NEED_SERVER_ON').'|false';
                                                }

                                            } elseif($rewards[$random]['type'] == 'money') { // si c'est des points boutique

                                                $money = $this->Connect->get_to_user('money', $this->request->data['pseudo']);
                                                $money = $money + intval($rewards[$random]['how']);
                                                $this->Connect->set_to_user('money', $money, $this->request->data['pseudo']);

                                                echo $this->Lang->get('VOTE_SUCCESS').' '.$this->Lang->get('REWARDS').' : <b>'.$rewards[$random]['how'].' '.$this->Configuration->get_money_name().'</b>.|true';

                                            } else {
                                                echo $this->Lang->get('INTERNAL_ERROR').'|false';
                                            }

                                        } elseif($rewards_type == 1) { // on fais toutes les commandes

                                            foreach ($rewards as $key => $value) { // on le fais pour toute les commandes

                                                if($value['type'] == 'server') { // si c'est une commande serveur

                                                    $config['0']['VoteConfiguration']['servers'] = unserialize($config['0']['VoteConfiguration']['servers']);
                                                    if(!empty($config['0']['VoteConfiguration']['servers'])) {
                                                        foreach ($config['0']['VoteConfiguration']['servers'] as $key => $value) {
                                                            $servers_online[] = $this->Server->online($value);
                                                        }
                                                    } else {
                                                        $servers_online = array($this->Server->online());
                                                    }
                                                    if(!in_array(false, $servers_online)) {
                                                        if(empty($config['0']['VoteConfiguration']['servers'])) {
                                                            $cmd = str_replace('{PLAYER}', $this->request->data['pseudo'], $value['command']);
                                                            $this->Server->send_command($cmd); // on envoie la commande puis enregistre le vote
                                                            $msg = str_replace('{PLAYER}', $this->request->data['pseudo'], $this->Lang->get('VOTE_SUCCESS_SERVER'));
                                                            $this->Server->send_command('broadcast '.$msg);
                                                        } else {
                                                            foreach ($config['0']['VoteConfiguration']['servers'] as $key => $value) {
                                                                $cmd = str_replace('{PLAYER}', $this->request->data['pseudo'], $value['command']);
                                                                $this->Server->send_command($cmd, $value); // on envoie la commande puis enregistre le vote
                                                                $msg = str_replace('{PLAYER}', $this->request->data['pseudo'], $this->Lang->get('VOTE_SUCCESS_SERVER'));
                                                                $this->Server->send_command('broadcast '.$msg, $value);
                                                            }
                                                        }

                                                        $success_msg[] = $value['name'];

                                                    } else {

                                                        $success_msg[] = 'server_error';
                                                    }

                                                } elseif($value['type'] == 'money') { // si c'est des points boutique

                                                    $money = $this->Connect->get_to_user('money', $this->request->data['pseudo']);
                                                    $money = $money + intval($value['how']);
                                                    $this->Connect->set_to_user('money', $money, $this->request->data['pseudo']);

                                                    $success_msg[] = $value['how'].' '.$this->Configuration->get_money_name();

                                                } else {
                                                    $success_msg[] = 'internal_error';
                                                }

                                            }

                                            if(in_array('server_error', $success_msg)) {

                                                echo $this->Lang->get('NEED_SERVER_ON').'|false';

                                            } elseif (in_array('internal_error', $success_msg)) {

                                                echo $this->Lang->get('INTERNAL_ERROR').'|false';

                                            } else {

                                                echo $this->Lang->get('VOTE_SUCCESS').' ! ';
                                                if(!empty($success_msg)) {
                                                    $this->Lang->get('REWARDS').' : ';

                                                    $i = 0;
                                                    $count = count($success_msg);
                                                    foreach ($success_msg as $k => $v) {
                                                        $i++;
                                                        echo '<b>'.$v.'</b>';
                                                        if($i < $count) {
                                                            echo ', ';
                                                        } else {
                                                            echo '.|true';
                                                        }
                                                    }
                                                } else {
                                                    echo '|true';
                                                }

                                            }

                                        } else {
                                            echo $this->Lang->get('INTERNAL_ERROR').'|false';
                                        }

                                    /*} else {
                                        echo $this->Lang->get('OUT_INVALID').'|false';
                                    }*/


                                } else {
                                    echo $this->Lang->get('ALREADY_VOTED').'|false';
                                }
                            } else {
                                echo $this->Lang->get('UNKNOWN_USERNAME').'|false';
                            }

                        }

        			} else { // STEP INCONNU
        				echo $this->Lang->get('STEP_INVALID').'|false';
        			}

        		} else {
        			echo $this->Lang->get('STEP_CANT_BE_NULL').'|false';
        		}
        	} else {
        		echo $this->Lang->get('REQUEST_NOT_POST').'|false';
        	}
        } else {
            echo $this->Lang->get('UNKNOWN_USERNAME').'|false';
        }
    }

    public function admin_index() {
        if($this->Connect->connect() AND $this->Connect->if_admin()) {
            $this->layout = "admin";
             
            $this->loadModel('VoteConfiguration');
            $vote = $this->VoteConfiguration->find('first');
            if(!empty($vote)) {
                $vote = $vote['VoteConfiguration'];
                $vote['rewards'] = unserialize($vote['rewards']);
            } else {
                $vote = array();
            }
            //debug($vote['rewards']);
            $this->set(compact('vote'));

            $this->loadModel('Server');
            if(!empty($vote['servers'])) {
                $vote['servers'] = unserialize($vote['servers']);
                foreach ($vote['servers'] as $key => $value) {
                    $d = $this->Server->find('first', array('conditions' => array('id' => $value)));
                    $selected_server[] = $d['Server']['id'];
                }
            } else {
                $selected_server = array();
            }
            $this->set(compact('selected_server'));

            $search_servers = $this->Server->find('all');
            if(!empty($search_servers)) {
                foreach ($search_servers as $v) {
                    $servers[$v['Server']['id']] = $v['Server']['name'];
                }
            } else {
                $servers = array();
            }
            $this->set(compact('servers'));

            $this->set('title_for_layout',$this->Lang->get('VOTE_TITLE'));
        } else {
            $this->redirect('/');
        }
    }

    public function admin_reset() {
        if($this->Connect->connect() AND $this->Connect->if_admin()) {
            $this->layout = null;
             
            $this->loadModel('Vote');
            $this->Vote->deleteAll(array('1' => '1'));
            $this->loadModel('User');
            $this->User->updateAll(array('vote' => 0));
            $this->History->set('RESET', 'vote');
            $this->Session->setFlash($this->Lang->get('RESET_VOTE_SUCCESS'), 'default.success');
            $this->redirect(array('controller' => 'voter', 'action' => 'index', 'admin' => true));
        }
    }

    public function admin_add_ajax() {
        if($this->Connect->connect() AND $this->Connect->if_admin()) {
            $this->layout = null;
             
            if($this->request->is('post')) {
                if(!empty($this->request->data['time_vote']) AND !empty($this->request->data['page_vote']) AND !empty($this->request->data['servers']) AND /*!empty($this->request->data['id_vote']) AND*/ $this->request->data['rewards_type'] == '0' OR $this->request->data['rewards_type'] == '1') {
                    if(!empty($this->request->data['reward_type']) AND $this->request->data['reward_type'] != 'undefined' AND !empty($this->request->data['reward_value']) AND $this->request->data['reward_value'] != 'undefined') {
                        $this->loadModel('VoteConfiguration');
                        /*
                        REWARDS -> serialize();

                        Structure = array(
                            array(
                                'type' => money/server
                                - 'how' => 10 - pour la money
                                - 'command' => say e - pour le server
                            )
                        
                        )

                        */
                        foreach ($this->request->data['reward_type'] as $key => $value) {
                            $k = explode('=', $value);
                            $k = $k[1];
                            $v = explode('=', $this->request->data['reward_value'][$key]);
                            $v = $v[1];
                            $rewards_array[][$k] = $v;
                        }

                        foreach ($this->request->data['reward_name'] as $key => $value) {
                            $k2 = explode('=', $value);
                            $k2 = $k2[1];
                            $v2 = explode('=', $this->request->data['reward_type'][$key]);
                            $v2 = $v2[1];
                            $rewards_name_array[] = $k2;
                        }

                        $i = 0;
                        foreach ($rewards_array as $k => $v) {
                            foreach ($v as $key => $value) {
                                $value = str_replace('+', ' ', $value);
                                $value = urldecode($value);
                                if($key == 'server') {
                                    $rewards_name_array[$i] = str_replace('+', ' ', $rewards_name_array[$i]);
                                    $rewards_name_array[$i] = urldecode($rewards_name_array[$i]);
                                    $rewards[] = array('type' => $key, 'name' => $rewards_name_array[$i], 'command' => $value);
                                } elseif($key == 'money') {
                                    $rewards_name_array[$i] = str_replace('+', ' ', $rewards_name_array[$i]);
                                    $rewards_name_array[$i] = urldecode($rewards_name_array[$i]);
                                    $rewards[] = array('type' => $key, 'name' => $rewards_name_array[$i], 'how' => $value);
                                } else {
                                    $rewards[] = null;
                                }
                            }
                            $i++;
                        }

                        $rewards = serialize($rewards);

                        $vote = $this->VoteConfiguration->find('first');
                        if(!empty($vote)) {
                            $this->VoteConfiguration->read(null, 1);
                        } else {
                            $this->VoteConfiguration->create();
                        }
                        $this->VoteConfiguration->set(array(
                            'time_vote' => $this->request->data['time_vote'],
                            'page_vote' => $this->request->data['page_vote'],
                            'id_vote' => /*$this->request->data['id_vote']*/0,
                            'rewards_type' => $this->request->data['rewards_type'],
                            'rewards' => $rewards,
                            'servers' => serialize($this->request->data['servers'])
                        ));
                        $this->VoteConfiguration->save();
                        $this->History->set('EDIT_CONFIG', 'vote');
                        $this->Session->setFlash($this->Lang->get('CONFIGURATION_SAVE'), 'default.success');
                        echo $this->Lang->get('CONFIGURATION_SAVE').'|true';
                    } else {
                        echo $this->Lang->get('COMPLETE_ALL_FIELDS').'|false';
                    }
                } else {
                    echo $this->Lang->get('COMPLETE_ALL_FIELDS').'|false';
                }
            } else {
                echo $this->Lang->get('NOT_POST').'|false';
            }
        } else {
            $this->redirect('/');
        }
    }
}