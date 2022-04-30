<?php

namespace blackjack200\motd;

use Logger;
use raklib\protocol\PacketSerializer;
use raklib\protocol\UnconnectedPing;
use raklib\protocol\UnconnectedPong;
use Thread;

class MotdThread extends Thread {
	private int $period;
	private string $last = '';
	private bool $running = true;
	private string $addr;
	private int $port;
	private Logger $logger;
	private int $timeout;
	private \ClassLoader $loader;
	private string $composerAutoload;


	public function __construct(string $composerAutoload, \ClassLoader $loader, Logger $logger, string $addr, int $port, int $period, int $timeout) {
		$this->composerAutoload = $composerAutoload;
		$this->loader = $loader;
		$this->timeout = $timeout;
		$this->logger = $logger;
		$this->addr = $addr;
		$this->port = $port;
		$this->period = $period;
	}

	public function getLastInfo() : string {
		return $this->synchronized(fn() => $this->last);
	}

	public function run() : void {
		require_once $this->composerAutoload;
		var_dump($this->composerAutoload);
		$this->loader->register(true);
		while ($this->running) {
			$this->synchronized(function() : void {
				$pk = new UnconnectedPing();
				$pk->clientId = random_int(0, PHP_INT_MAX);
				$pk->sendPingTime = intdiv(hrtime(true), 1000000);
				$s = new PacketSerializer();
				$pk->encode($s);
				$buf = $s->getBuffer();
				$sock = stream_socket_client("udp://$this->addr:$this->port", $errno, $errstr, $this->timeout);
				if ($errno !== 0) {
					$this->logger->error("$errstr ($errno)");
					return;
				}
				if ($sock === false) {
					$this->logger->error("Could not connect to $this->addr:$this->port");
					return;
				}
				stream_set_timeout($sock, $this->timeout);
				fwrite($sock, $buf);
				$respBuf = fread($sock, 65535);
				fclose($sock);
				$resp = new UnconnectedPong();
				$resp->decode(new PacketSerializer($respBuf));
				$this->last = $resp->serverName;
			});
			sleep($this->period);
		}
	}

	public function shutdown() : void {
		$this->running = false;
	}
}