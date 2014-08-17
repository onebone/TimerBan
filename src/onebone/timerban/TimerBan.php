<?php

namespace onebone\timerban;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\BanEntry;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;

class TimerBan extends PluginBase implements Listener{
	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "timerban":
				$player = array_shift($params);
				$after = array_shift($params);
				$reason = implode(" ", $params);
				if(trim($player) === "" or !is_numeric($after)){
					$sender->sendMessage("[TimerBan] Usage: /timerban <player> <time> [reason..]");
					break;
				}
				$after = round($after, 2);
				$secAfter = $after*3600;

				$due = $secAfter + time();
				$dueDate = new \DateTime(date("Y/m/d h:i:s", $due));
				
				$this->getServer()->getNameBans()->addBan($player, $reason, $dueDate, $sender->getName());
				$sender->sendMessage("[TimerBan] $player has been banned for $after hour(s).");
				
				if(($player = $this->getServer()->getPlayer($player)) instanceof Player){
					$player->kick("You have been banned for $after hour(s).");
				}
				break;
			case "timerbanip":
				$ip = array_shift($params);
				$after = array_shift($params);
				$reason = implode(" ", $params);
				if(trim($ip) === "" or !is_numeric($after)){
					$sender->sendMessage("[TimerBan] Usage: /timerban <player> <time> [reason..]");
					break;
				}
				$after = round($after, 2);
				$secAfter = $after*3600;
				if(preg_match("/^((25[0-5]|2[0-4][0-9]|[01][0-9]{1,2}\\.){3}(2[0-5]|2[0-4][0-9]|[01][0-9]{1,2}))$/", $ip)){ // I'm get used to Java's regular expression
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
				$dueDate = new \DateTime(date("Y/m/d h:i:s", $due));

				$this->getServer()->getIPBans()->addBan($ip, $reason, $dueDate, $sender->getName());
				$sender->sendMessage("[TimerBan] $ip has been banned for $after hours.");
				break;
		}
		return true;
	}
}