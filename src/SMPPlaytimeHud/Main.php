<?php
declare(strict_types=1);

namespace SMPPlaytimeHud;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

// ScoreHud
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;

final class Main extends PluginBase implements Listener{

    /** @var array<string,int> username(lowercase) => joinTimestamp */
    private array $joinTimes = [];

    /** @var Config */
    private Config $data;

    /** @var array<string,int> username(lowercase) => totalSeconds */
    private array $totals = [];

    public function onEnable() : void{
        @mkdir($this->getDataFolder());
        $this->data = new Config($this->getDataFolder() . "playtime.yml", Config::YAML, [
            "totals" => []
        ]);

        $raw = $this->data->get("totals", []);
        if(is_array($raw)){
            foreach($raw as $name => $seconds){
                if(is_string($name) && (is_int($seconds) || is_float($seconds) || is_string($seconds))){
                    $this->totals[strtolower($name)] = (int)$seconds;
                }
            }
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable() : void{
        // Flush everyone currently tracked
        foreach($this->getServer()->getOnlinePlayers() as $player){
            $this->flushSession($player->getName());
        }

        $this->data->set("totals", $this->totals);
        $this->data->save();
    }

    public function onJoin(PlayerJoinEvent $event) : void{
        $name = strtolower($event->getPlayer()->getName());
        $this->joinTimes[$name] = time();
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $this->flushSession($event->getPlayer()->getName());
    }

    public function onKick(PlayerKickEvent $event) : void{
        $this->flushSession($event->getPlayer()->getName());
    }

    private function flushSession(string $playerName) : void{
        $name = strtolower($playerName);

        if(isset($this->joinTimes[$name])){
            $session = max(0, time() - $this->joinTimes[$name]);
            $this->totals[$name] = ($this->totals[$name] ?? 0) + $session;
            unset($this->joinTimes[$name]);
        }
    }

    /**
     * ScoreHud tag handler
     * Tag to use in ScoreHud config: {smp.playtime}
     */
    public function onTagUpdate(PlayerTagUpdateEvent $event) : void{
        $tag = $event->getTag();

        if($tag->getName() !== "smp.playtime"){
            return;
        }

        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        $total = $this->totals[$name] ?? 0;
        if(isset($this->joinTimes[$name])){
            $total += max(0, time() - $this->joinTimes[$name]);
        }

        $tag->setValue($this->formatDuration($total));
    }

    private function formatDuration(int $seconds) : string{
        $seconds = max(0, $seconds);

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;

        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;

        $mins = intdiv($seconds, 60);

        if($days > 0){
            return $days . "d " . $hours . "h";
        }
        if($hours > 0){
            return $hours . "h " . $mins . "m";
        }
        return $mins . "m";
    }
}
