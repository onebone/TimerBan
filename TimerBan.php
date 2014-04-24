<?php

/*
__PocketMine Plugin__
name=TimerBan
version=1.2
author=onebone
apiversion=10,11
class=TimerBan
*/

/*
=====================
    CHANGE LOG
=====================

v1.0 : Initial release
v1.1 : Added temp IP ban system
v1.2 : Configuration, new time indicate format, bug fix

*/

class TimerBan implements Plugin{
	private $api, $ban, $banIP, $config;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		@mkdir(DATA_PATH."plugins/TimerBan/");
		$this->ban = new Config(DATA_PATH."plugins/TimerBan/banList.yml", CONFIG_YAML);
		$this->banIP = new Config(DATA_PATH."plugins/TimerBan/banIPList.yml", CONFIG_YAML);
		$this->config = new Config(DATA_PATH."plugins/TimerBan/config.yml", CONFIG_YAML, array(
			"frequent-save" => false
		));
		$this->config->save();
		$this->api->addHandler("player.connect", array($this, "onConnect"));
		$this->api->event("server.close", array($this, "onClose"));
		$this->api->console->register("timerban", "<add | remove | list | reload> [player]", array($this, "onHandleCommand"));
		$this->api->console->register("timerbanip", "<add | remove | list | reload> [player | IP]", array($this, "onHandleCommand"));
	}
	
	public function __destruct(){}
	
	public function onConnect($data){
		if((($due = $this->ban->get($data->iusername)) !== false and !($isIP = false)) or (($due = $this->banIP->get($data->ip)) !== false and ($isIP = true))){
			$due = (int) $due;
			if($due > time()){
				$nowTime = time();
				$remain = ($due - $nowTime) / 60;
				$hours = (int)($remain / 60);
				$mins = $remain % 60;
				$hourS = $hours > 1 ? "s":"";
				$minS = $mins > 1 ? "s":"";
				$data->close("You are banned remaining {$hours} hour{$hourS} {$mins} minute{$minS}", false);
			}else{
				$isIP ? $this->banIP->remove($data->ip) : $this->ban->remove($data->iusername);
			}
		}
	}
	
	public function onClose(){
		$this->ban->save();
		$this->banIP->save();
	}
	
	public function onHandleCommand($cmd, $param, $issuer){
		$output = "[TimerBan] ";
		switch($cmd){
			case "timerban":
			$sub = array_shift($param);
			switch($sub){
				case "add":
				case "ban":
				$player = array_shift($param);
				$time = array_shift($param);
				if(trim($player) == "" or trim($time) == "" or !is_numeric($time)){
					$output .= "Usage : /timerban add <player> <hours>";
					break;
				}
				$p = $this->api->player->get($player, false);
				if(!$p instanceof Player){
					$p = $this->api->player->get($player);
					if(!$p instanceof Player){
						$p = $player;
					}
				}
				$user = false;
				if($p instanceof Player){
					$user = $p;
					$p = $p->iusername;
				}
				$now = time();
				$time = round($time, 2);
				if($time <= 0){
					return "[TimerBan] $time is invalid time";
				}
				$due = (int)($now + ($time * 3600));
				$remain = ($due - $now) / 60;
				$hours = (int)($remain / 60);
				$mins = $remain % 60;
				$hourS = $hours > 1 ? "s":"";
				$minS = $mins > 1 ? "s":"";
				$this->close($user, "You are banned remaining {$hours} hour{$hourS} {$mins} minute{$minS}");
				$this->ban->set($p, $due);
				if($this->config->get("frequent-save")){
					$this->ban->save();
				}
				$output .= "Banned $p for {$hours} hour{$hourS} {$mins} minute{$minS}.";
				break;
				case "remove":
				case "pardon":
				$player = array_shift($param);
				if(trim($player) == ""){
					$output .= "Usage : /timerban <remove | pardon> <player>";
					break;
				}
				$result = $this->ban->exists($player);
				if(!$result){
					$output .= "$player does not exist in list.";
					break;
				}else{
					$this->ban->remove($player);
					if($this->config->get("frequent-save")){
						$this->ban->save();
					}
					$output .= "Has been removed $player from the list.";
				}
				break;
				case "list":
				$page = array_shift($param);
				$page = max($page, 1);
				$all = $this->ban->getAll();
				$values = array();
				$cnt = count($all);
				$cnt = (int) ceil($cnt / 5);
				$page = min($cnt, $page);
				$now = 1;
				$output .= "- Showing ban list page $page of $cnt - \n";
				$nowTime = time();
				foreach($all as $key => $data){
					$due = $data;
					$cur = (int) ceil($now / 5);
					if($cur == $page){
						$remain = ($due - $nowTime) / 60;
						$hours = (int)($remain / 60);
						$mins = $remain % 60;
						$hourS = $hours > 1 ? "s":"";
						$minS = $mins > 1 ? "s":"";
						if($remain <= 0){
							$this->ban->remove($key);
							continue;
						}
						$output .= "[".$key."] :: {$hours} hour{$hourS} {$mins} minute{$minS} remaining\n";
					}elseif($cur > $page){
						break;
					}
					++$now;
				}
				break;
				case "reload":
				$this->ban = new Config(DATA_PATH."plugins/TimerBan/banList.yml", CONFIG_YAML);
				return;
				default:
				$output .= "Usage : /timerban <add | remove | list | reload>";
			}
			break;
			case "timerbanip":
			$sub = array_shift($param);
			if(trim($sub) == ""): // Yeah, I got this ridiculously retarded IF formatting @sekjun98888877777778888888888888
				$output .= "Usage: /timerbanip <add | remove | list | reload> [player | IP]";
				break;
			endif;
			switch($sub){
				case "add":
				case "ban":
				$player = array_shift($param);
				$time = array_shift($param);
				if(trim($player) == "" or trim($time) == "" or !is_numeric($time)){
					$output .= "Usage : /timerbanip add <player | IP> <hours>";
					break;
				}
				$p = $this->api->player->get($player, false);
				if(!$p instanceof Player){
					$p = $this->api->player->get($player);
					if(!$p instanceof Player){
						$p = $player;
					}
				}
				$user = false;
				if($p instanceof Player){
					$user = $p;
					$p = $p->ip;
				}
				$time = round($time, 2);
				if($time <= 0){
					return "[TimerBan] $time is invalid time";
				}
				$now = time();
				$due = (int)($now + ($time * 3600));
				$remain = ($due - $now) / 60;
				$hours = (int)($remain / 60);
				$mins = $remain % 60;
				$hourS = $hours > 1 ? "s":"";
				$minS = $mins > 1 ? "s":"";
				$this->banIP->set($p, $due);
				if($this->config->get("frequent-save")){
					$this->banIP->save();
				}
				$this->close($user, "You are IP banned for $hour hour{$hourS} $mins minute{$minS}");
				$p = ($user instanceof Player) ? $user->username : $p;
				$output .= "Banned $p for $hours hour{$hourS} $mins minute{$minS}.";
				break;
				case "remove":
				case "pardon":
				$player = array_shift($param);
				if(trim($player) == ""){
					$output .= "Usage : /timerbanip <remove | pardon> <IP>";
					break;
				}
				$result = $this->banIP->exists($player);
				if(!$result){
					$output .= "$player does not exist in list.";
					break;
				}else{
					$this->banIP->remove($player);
					if($this->config->get("frequent-save")){
						$this->banIP->save();
					}
					$output .= "Has been removed $player from the list.";
				}
				break;
				case "list":
				$page = array_shift($param);
				$page = max($page, 1);
				$all = $this->banIP->getAll();
				$values = array();
				$cnt = count($all);
				$cnt = (int) ceil($cnt / 5);
				$page = min($cnt, $page);
				$nowTime = time();
				$now = 1;
				$output .= "- Showing banned IP list page $page of $cnt - \n";
				foreach($all as $key => $data){
					$due = $data;
					$cur = (int) ceil($now / 5);
					if($cur == $page){
						$remain = ($due - $nowTime) / 60;
						$hours = (int)($remain / 60);
						$mins = $remain % 60;
						$hourS = $hours > 1 ? "s":"";
						$minS = $mins > 1 ? "s":"";
						if($remain <= 0){
							$this->ban->remove($key);
							continue;
						}
						$output .= "[".$key."] :: {$hours} hour{$hourS} {$mins} minute{$minS} remaining\n";
					}elseif($cur > $page){
						break;
					}
					++$now;
				}
				break;
				case "reload":
				$this->banIP = new COnfig(DATA_PATH."plugins/TimerBan/banIPList.yml", CONFIG_YAML);
				return;
				default:
				$output .= "Usage: /timerbanip <add | remove | list | reload> [player | IP]";
			}
			break;
		}
		return $output;
	}
	
	public function close($player, $msg){
		if($player instanceof Player){
			$player->sendChat($msg);
			$player->blocked = true;
			$this->api->schedule(40, array($this, "forceKick"), array($player, "You are banned"));
		}
	}
	
	public function forceKick($data){
		if(($player = $this->api->player->get($data[0], false)) instanceof Player){
			$player->close($data[1]);
		}
	}
}