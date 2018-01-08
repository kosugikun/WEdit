<?php

namespace KurasakiEdit;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\utils\Utils;

class main extends PluginBase implements Listener{
	
	public function onLoad() {
        $response = Utils::getURL("http://www.example.com/version.txt?" . time() . "mt_rand");    //プラグインのバージョンが記載されているファイルが置かれているURL。?とtime()とmt_rand()は後で解説

        if($response !== false) {    //接続できなかった場合はfalseを返すのでここで評価
            $response = str_replace("\n", "", $response);    //文字列の最後は改行されているのでそれを取り除く
           
            if($this->getDescription()->getVersion() !== $response) {    //plugin.ymlに記載されているバージョンと$responseを比較
               
                $message = "KurasakiEditの新しいバージョンがあります！　⇒" . $this->getDescription()->getWebsite();    //お知らせとplugin.ymlに記載されているwabsite欄のURLを表示
                $this->getServer()->getLogger()->notice($message);
            }
        }
    }

	public function onEnable(){
		$this->getlogger()->info("KurasakiEditを起動しました。");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool{
		if($sender instanceof Player){
			if($label === 'air'){
				if(!isset($args[0])) return false;
				$this->replace($sender, $args[0]);
			}elseif($label === 'bricks'){
				$this->random($sender, '98', 6, ['98:1', '98:2']);
			}elseif($label === 'copy'){
				$this->copy($sender);
			}elseif($label === 'cut'){
				$this->set($sender);
			}elseif($label === 'e'){
				$this->e($sender);
			}elseif($label === 'paste'){
				$this->paste($sender);
			}elseif($label === 'random'){
				if(!isset($args[2])) return false;
				$count = count($args);
				for($i = 2; $i < $count; ++$i) $after[] = $args[$i];
				$this->random($sender, $args[0], $args[1], $after);
			}elseif($label === 'replace'){
				if(!isset($args[1])) return false;
				$this->replace($sender, $args[0], $args[1]);
			}elseif($label === 'set'){
				if(!isset($args[0])) return false;
				$this->set($sender, $args[0]);
			}elseif($label === 'undo'){
				$this->undo($sender);
			}
		}else{
			$sender->sendMessage('§cコンソールパネルからは操作できません');
		}
		return true;
	}

	public function BlockBreak(BlockBreakEvent $event){
		$this->isSetting($event, 0, 1);
	}

	public function BlockPlace(BlockPlaceEvent $event){
		$this->isSetting($event, 1, 0);
	}

	public function isSetting($event, $own, $two){
		$id = $event->getItem()->getId();
		if($id === 137){
			$player = $event->getPlayer();
			$name = $player->getName();
			if(!isset($this->setting[$name][$own])){
				$event->setCancelled();
				$block = $event->getBlock();
				$x = $block->x;
				$y = $block->y;
				$z = $block->z;
				$this->setting[$name][$own] = [$x, $y, $z];
				if(isset($this->setting[$name][$two])){
					$change = $this->change($name);
					$player->sendMessage('座標Bが設定されました '.$x.', '.$y.', '.$z.' (合計: '.$change[9].')');
				}else{
					$player->sendMessage('座標Aが設定されました '.$x.', '.$y.', '.$z);
				}
			}
		}
	}

	public function PlayerInteract(PlayerInteractEvent $event){
		$action = $event->getAction();
		if($action === 0 or $action === 1){
			$player = $event->getPlayer();
			if($player->isOp()){
				$id = $event->getItem()->getId();
				$block = $event->getBlock();
				if($id === 345){
					$x = $block->x;
					$y = $block->y;
					$z = $block->z;
					$player->sendMessage('このブロックの座標を取得しました '.$x.', '.$y.', '.$z);
				}elseif($id === 347){
					$name = $block->getName();
					$id = $block->getId();
					$meta = $block->getDamage();
					$player->sendMessage('このブロックの情報を取得しました '.$name.' ('.$id.':'.$meta.')');
				}
			}
		}
	}

	public function copy($player){
		$name = $player->getName();
		if(isset($this->setting[$name][0], $this->setting[$name][1])){
			$server = $this->getServer();
			$change = $this->change($name);
			$server->broadcastMessage($name.'がコピーを開始します… (合計: '.$change[9].')');
			$level = $player->getLevel();
			$key = 0;
			for($x = $change[3]; $x <= $change[0]; ++$x){
				for($y = $change[4]; $y <= $change[1]; ++$y){
					for($z = $change[5]; $z <= $change[2]; ++$z){
						$id = $level->getBlockIdAt($x, $y ,$z);
						$meta = $level->getBlockDataAt($x, $y ,$z);
						$block = Block::get($id, $meta);
						$copy[$key] = $block;
						++$key;
					}
				}
			}
			$this->setting[$name][3] = [$copy, $change[6], $change[7], $change[8]];
			$server->broadcastMessage($name.'のコピーが終了しました (X: '.$change[6].' Y: '.$change[7].' Z: '.$change[8].')');
		}else{
			$player->sendMessage('§cコマンドブロックで範囲を指定してください');
		}
	}

	public function change($name){
		$setting = $this->setting[$name];
		$pos_own = $setting[0];
		$pos_two = $setting[1];
		$change[0] = max($pos_own[0], $pos_two[0]);
		$change[1] = max($pos_own[1], $pos_two[1]);
		$change[2] = max($pos_own[2], $pos_two[2]);
		$change[3] = min($pos_own[0], $pos_two[0]);
		$change[4] = min($pos_own[1], $pos_two[1]);
		$change[5] = min($pos_own[2], $pos_two[2]);
		$change[6] = ($change[0] - $change[3]) + 1;
		$change[7] = ($change[1] - $change[4]) + 1;
		$change[8] = ($change[2] - $change[5]) + 1;
		$change[9] = $change[6] * $change[7] * $change[8];
		return $change;
	}

	public function e($player){
		$name = $player->getName();
		if(isset($this->setting[$name][0]) or isset($this->setting[$name][1])){
			unset($this->setting[$name][0], $this->setting[$name][1], $this->setting[$name][2]);
			$player->sendMessage('設定した座標のデータを削除しました');
		}else{
			$player->sendMessage('§c座標のデータは設定されていません');
		}
	}

	public function fromString($string){
		$explode = explode(':', $string);
		if(255 < (int) $explode[0] or 0 > (int) $explode[0]) $explode[0] = 0;
		if(!isset($explode[1])) $explode[1] = 0;
		if(15 < (int) $explode[1] or 0 > (int) $explode[1]) $explode[1] = 0;
		return Block::get((int) $explode[0], (int) $explode[1]);
	}

	public function paste($player){
		$name = $player->getName();
		if(isset($this->setting[$name][0], $this->setting[$name][1], $this->setting[$name][3])){
			$change = $this->change($name);
			$copy = $this->setting[$name][3];
			if($change[6] === $copy[1] and $change[7] === $copy[2] and $change[8] === $copy[3]){
				$server = $this->getServer();
				$server->broadcastMessage($name.'がペーストを開始します… (合計: '.$change[9].')');
				$level = $player->getLevel();
				$paste = $copy[0];
				$key = 0;
				for($x = $change[3]; $x <= $change[0]; ++$x){
					for($y = $change[4]; $y <= $change[1]; ++$y){
						for($z = $change[5]; $z <= $change[2]; ++$z){
							$id = $level->getBlockIdAt($x, $y ,$z);
							$paste_id = $paste[$key]->getId();
							$meta = $level->getBlockDataAt($x, $y ,$z);
							$paste_meta = $paste[$key]->getDamage();
							if($id !== $paste_id or $meta !== $paste_meta){
								$vector = new Vector3($x, $y, $z);
								$block = Block::get($id, $meta);
								$undo[] = [$vector, $block];
								$level->setBlock($vector, $paste[$key]);
							}
							++$key;
						}
					}
				}
				$this->setting[$name][2] = $undo;
				$count = count($undo);
				$server->broadcastMessage($name.'のペーストが終了しました (変更: '.$count.')');
			}else{
				$player->sendMessage('§c範囲が正確でないた、ペーストが出来ません');
			}
		}else{
			$player->sendMessage('§cコマンドブロックで範囲を指定してください');
		}
	}

	public function replace($player, $before, $after = 0){
		$name = $player->getName();
		if(isset($this->setting[$name][0], $this->setting[$name][1])){
			$server = $this->getServer();
			$before = $this->fromString($before);
			$after = $this->fromString($after);
			$before_name = $before->getName();
			$after_name = $after->getName();
			$change = $this->change($name);
			$server->broadcastMessage($name.'が'.$before_name.'を'.$after_name.'に変更します… (合計: '.$change[9].')');
			$level = $player->getLevel();
			$before_id = $before->getId();
			$before_meta = $before->getDamage();
			$undo = [];
			for($x = $change[3]; $x <= $change[0]; ++$x){
				for($y = $change[4]; $y <= $change[1]; ++$y){
					for($z = $change[5]; $z <= $change[2]; ++$z){
						$id = $level->getBlockIdAt($x, $y ,$z);
						$meta = $level->getBlockDataAt($x, $y ,$z);
						if($id === $before_id and $meta === $before_meta){
							$vector = new Vector3($x, $y, $z);
							$undo[] = [$vector, $before];
							$level->setBlock($vector, $after);
						}
					}
				}
			}
			$this->setting[$name][2] = $undo;
			$count = count($undo);
			$server->broadcastMessage($name.'の変更が終了しました (変更: '.$count.')');
		}else{
			$player->sendMessage('§cコマンドブロックで範囲を指定してください');
		}
		return true;
	}

	public function random($player, $before, int $rate, array $after){
		$name = $player->getName();
		if(isset($this->setting[$name][0], $this->setting[$name][1])){
			$server = $this->getServer();
			$before = $this->fromString($before);
			$before_name = $before->getName();
			$change = $this->change($name);
			$server->broadcastMessage($name.'が'.$before_name.'を適当に変更します… (合計: '.$change[9].')');
			$level = $player->getLevel();
			$before_id = $before->getId();
			$before_meta = $before->getDamage();
			$random = [];
			for($x = $change[3]; $x <= $change[0]; ++$x){
				for($y = $change[4]; $y <= $change[1]; ++$y){
					for($z = $change[5]; $z <= $change[2]; ++$z){
						$id = $level->getBlockIdAt($x, $y ,$z);
						$meta = $level->getBlockDataAt($x, $y ,$z);
						if($id === $before_id and $meta === $before_meta){
							$vector = new Vector3($x, $y, $z);
							$random[] = $vector;
						}
					}
				}
			}
			$count = count($random);
			if($rate <= 0) $rate = 1;
			if($count <= $rate){
				$player->sendMessage('§c必要な数のブロックがありません');
			}else{
				$ceil = ceil($count / $rate);
				$array_rand = array_rand($random, $ceil);
				$count = count($after);
				foreach($after as $key => $value) $after[$key] = $this->fromString($value);
				foreach($array_rand as $value){
					$undo[] = [$random[$value], $before];
					$rand = mt_rand(0, $count - 1);
					$level->setBlock($random[$value], $after[$rand]);
				}
				$this->setting[$name][2] = $undo;
				$server->broadcastMessage($name.'の変更が終了しました (変更: '.$ceil.')');
			}
		}else{
			$player->sendMessage('§cコマンドブロックで範囲を指定してください');
		}
	}

	public function set($player, $after = 0){
		$name = $player->getName();
		if(isset($this->setting[$name][0], $this->setting[$name][1])){
			$after = $this->fromString($after);
			$after_name = $after->getName();
			$change = $this->change($name);
			$server = $this->getServer();
			$server->broadcastMessage($name.'が'.$after_name.'に変更を開始します… (合計: '.$change[9].')');
			$level = $player->getLevel();
			$after_id = $after->getId();
			$after_meta = $after->getDamage();
			$undo = [];
			for($x = $change[3]; $x <= $change[0]; ++$x){
				for($y = $change[4]; $y <= $change[1]; ++$y){
					for($z = $change[5]; $z <= $change[2]; ++$z){
						$id = $level->getBlockIdAt($x, $y, $z);
						$meta = $level->getBlockDataAt($x, $y, $z);
						if($id !== $after_id or $meta !== $after_meta){
							$vector = new Vector3($x, $y, $z);
							$block = Block::get($id, $meta);
							$undo[] = [$vector, $block];
							$level->setBlock($vector, $after);
						}
					}
				}
			}
			$this->setting[$name][2] = $undo;
			$count = count($undo);
			$server->broadcastMessage($name.'の変更が終了しました (変更: '.$count.')');
		}else{
			$player->sendMessage('§cコマンドブロックで範囲を指定してください');
		}
	}

	public function undo($player){
		$name = $player->getName();
		if(isset($this->setting[$name][2])){
			$undo = $this->setting[$name][2];
			$count = count($undo);
			$server = $this->getServer();
			$server->broadcastMessage($name.'が復元を開始します… (合計: '.$count.')');
			$level = $player->getLevel();
			foreach($undo as $value) $level->setBlock($value[0], $value[1]);
			$server->broadcastMessage($name.'の復元が終了しました');
		}else{
			$player->sendMessage('§c復元する為のデータが存在しません');
		}
	}

}
