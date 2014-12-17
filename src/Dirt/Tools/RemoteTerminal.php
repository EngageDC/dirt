<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class RemoteTerminal implements TerminalInterface {

  private $ssh;
  private $output;
  private $exitOnError = true;
  private $isSession = false;
  private $sessionCommands;

  public function __construct($hostname, $port, $keyfile, $username, $output) {
    $this->output = $output;

    $this->ssh = new \Net_SSH2($hostname, $port);
    $key = new \Crypt_RSA();
    $key->loadKey(file_get_contents($keyfile));

    if (!$this->ssh->login($username, $key)) {
      $this->output->writeln('<error>Error: Authentication failed</error>');
      exit(1);
    }
  }

  public function getSSHConnection() {
    return $this->ssh;
  }

  public function ignoreError() {
    $this->exitOnError = false;
    return $this;
  }

  public function startSession() {
    $this->sessionCommands = [];
    $this->isSession = true;
    return $this;
  }

  public function executeSession() {
    $this->isSession = false;
    return $this->execute(implode(' && ', $this->sessionCommands));
  }

  public function run($command) {
    if ($this->isSession) {
      $this->sessionCommands[] = $command;
      return '';
    }
    else {
      return $this->execute($command);
    }
  }

  private function execute($command) {
    $response = $this->ssh->exec($command);

    if ($this->ssh->getExitStatus() != 0) {
      if($this->exitOnError) {
        $this->output->writeln('<error>Error! Unexpected response: '. trim($response) .'</error>');
        exit(1);
      }
      else {
        $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
      }
    }

    $this->sessionCommands = [];
    $this->exitOnError = true;
    return $response;
  }
}
