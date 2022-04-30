<?php

namespace blackjack200\motd;

use pocketmine\event\Listener;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use raklib\server\ipc\UserToRakLibThreadMessageSender;
use Webmozart\PathUtil\Path;

class MotdSync extends PluginBase implements Listener {
	private MotdThread $thread;

	protected function onEnable() : void {
		$this->saveDefaultConfig();
		$conf = $this->getConfig();
		$this->thread = new MotdThread(
			Path::join(__DIR__, "./../../../", "vendor", "autoload.php"),
			$this->getServer()->getLoader(),
			new \PrefixedLogger($this->getLogger(), "MotdSyncThread"),
			$conf->get("addr"),
			$conf->get("port"),
			$conf->get("period"),
			$conf->get("timeout"),
		);
		$this->thread->start();
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->realStart()), 40);
	}

	protected function onDisable() : void {
		$this->thread->shutdown();
	}

	protected function realStart() : void {
		$conf = $this->getConfig();
		$validIfaces = [];
		$prop = new \ReflectionProperty(RakLibInterface::class, "interface");
		$prop->setAccessible(true);
		$ifaces = $this->getServer()->getNetwork()->getInterfaces();
		foreach ($ifaces as $iface) {
			if ($iface instanceof RakLibInterface) {
				$validIfaces[] = [$iface, $prop->getValue($iface)];
			}
		}
		$task = new ClosureTask(function() use ($validIfaces) : void {
			$info = $this->thread->getLastInfo();
			/**
			 * @var RakLibInterface $iface
			 * @var UserToRakLibThreadMessageSender $i
			 */
			foreach ($validIfaces as [$iface, $i]) {
				$i->setName($info);
				//var_dump($info);
			}
		});
		$this->getScheduler()->scheduleRepeatingTask($task, 1);
	}
}