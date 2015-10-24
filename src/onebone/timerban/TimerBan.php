<?php

namespace onebone\timerban;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\BanEntry;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\utils\Config;
use pocketmine\Player;

class TimerBan extends PluginBase implements Listener{
	private $banList;
	private $ipBanList;

	/**
	 * Bans player for specific time
	 *
	 * @var Player|string	$player
	 * @var float					$time
	 *
	 * @return bool
	 */
	public function banPlayer($player, $time){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		if($this->banList->exists($player)){
			return false;
		}
		$seconds = $time * 3600; // hours to seconds

		$due = $seconds + time();
		$this->banList->set($player, $due);
		return true;
	}

	/**
	 * Bans IP address for specific time
	 *
	 * @var Player|string	$address
	 * @var float		$time
	 *
	 * @return bool
	 */
	public function banAddress($address, $time){
		if($player instanceof Player){
			$player = $player->getAddress();
		}
		if(filter_var($address, FILTER_VALIDATE_IP)){
			if($this->ipBanList->exists($address)){
				return false;
			}
			$seconds = $time * 3600;

			$due = $seconds + time();
			$this->ipBanList->set($address, $due);
			return true;
		}else{
			return false;
		}
	}

	/**
	 * Returns if player is banned or not
	 *
	 * @var Player|string	$player
	 *
	 * @return bool|int
	 */
	public function isBanned($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);

		if($this->banList->exists($player)){
			return $this->banList->get($player);
		}
		return false;
	}

	/**
	 * Returns if player is banned or not
	 *
	 * @var Player|string	$address
	 *
	 * @return bool|int
	 */
	public function isAddressBanned($address){
		if($address instanceof Player){
			$address = $address->getAddress();
		}

		if($this->ipBanList->exists($address)){
			return $this->ipBanList->get($address);
		}
		return false;
	}

	// NON API PART
	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		$this->banList = new Config($this->getDataFolder()."BanList.yml", Config::YAML);
		$this->ipBanList = new Config($this->getDataFolder()."IPBanList.yml", Config::YAML);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerLogin(PlayerPreLoginEvent $event){
		$player = $event->getPlayer();
		$now = time();
		if($this->banList->exists(strtolower($player->getName()))){
			if($this->banList->get(strtolower($player->getName())) > $now){
				$player->close("", "You are banned");
				$event->setCancelled();
			}else{
				$this->banList->remove(strtolower($player->getName()));
			}
			return;
		}
		if($this->ipBanList->exists($player->getAddress())){
			if($this->ipBanList->get($player->getAddress()) > $now){
				$player->close("", "You are banned");
				$event->setCancelled();
			}else{
				$this->ipBanList->remove($player->getAddress());
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "timerban":
				$sub = array_shift($params);
				switch($sub){
					case "add":
					$player = array_shift($params);
					$after = array_shift($params);
					$reason = implode(" ", $params);
					if(trim($player) === "" or !is_numeric($after)){
						$sender->sendMessage("[TimerBan] Usage: /timerban add <player> <time> [reason..]");
						break;
					}
					$after = round($after, 2);
					$secAfter = $after*3600;

					$due = $secAfter + time();

					$this->banList->set(strtolower($player), $due);
					$this->banList->save();

					$sender->sendMessage("[TimerBan] $player has been banned for $after hour(s).");

					if(($player = $this->getServer()->getPlayer($player)) instanceof Player){
						$player->kick("You have been banned for $after hour(s).");
					}
					break;
					case "remove":
					case "pardon":
					$player = array_shift($params);

					if(trim($player) === ""){
						$sender->sendMessage("[TimerBan] Usage: /timerban remove <player>");
						break;
					}

					if(!$this->banList->exists($player)){
						$sender->sendMessage("[TimerBan] There is no player named \"$player\"");
						break;
					}

					$this->banList->remove($player);
					$this->banList->save();
					$sender->sendMessage("[TimerBan] \"$player\" have been removed from the ban list.");
					break;
					case "list":
					$list = $this->banList->getAll();
					$output = "Ban list : \n";
					foreach($list as $key => $due){
						$output .= $key.", ";
					}
					$output = substr($output, 0, -2);
					$sender->sendMessage($output);
					break;
					default:
					$sender->sendMessage("[TimerBan] Usage: ".$command->getUsage());
				}
				break;
			case "timerbanip":
				$sub = array_shift($params);
				switch($sub){
					case "add":
					$ip = array_shift($params);
					$after = array_shift($params);
					$reason = implode(" ", $params);
					if(trim($ip) === "" or !is_numeric($after)){
						$sender->sendMessage("[TimerBan] Usage: /timerban <player> <time> [reason..]");
						break;
					}
					$after = round($after, 2);
					$secAfter = $after*3600;
					if(filter_var($ip, FILTER_VALIDATE_IP)){
						foreach($this->getServer()->getOnlinePlayers() as $player){
							if($player->getAddress() === $ip){
								$player->kick("You have been banned for $after hour(s).");
								break;
							}
						}
					}else{
						$player = $this->getServer()->getPlayer($ip);
						if($player instanceof Player){
							$ip = $player->getAddress();
							$player->kick("You have been banned for $after hour(s).");
						}
					}

					$due = $secAfter + time();

					$this->ipBanList->set($ip, $due);
					$this->ipBanList->save();

					$sender->sendMessage("[TimerBan] $ip has been banned for $after hours.");
					break;
					case "remove":
					case "pardon":
					$player = array_shift($params);

					if(trim($player) === ""){
						$sender->sendMessage("[TimerBan] Usage: /timerban remove <player>");
						break;
					}

					if(!$this->ipBanList->exists($player)){
						$sender->sendMessage("[TimerBan] There is no player with IP \"$player\"");
						break;
					}

					$this->ipBanList->remove($player);
					$this->ipBanList->save();
					$sender->sendMessage("[TimerBan] \"$player\" have been removed from the ban list.");
					break;
					case "list":
					$list = $this->ipBanList->getAll();
					$output = "IP Ban list : \n";
					foreach($list as $key => $due){
						$output .= $key.", ";
					}
					$output = substr($output, 0, -2);
					$sender->sendMessage($output);
					break;
					default:
					$sender->sendMessage("[TimerBan] Usage: ".$command->getUsage());
				}
		}
		return true;
	}
}
