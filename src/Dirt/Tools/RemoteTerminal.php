<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class RemoteTerminal {

    private $ssh;
    private $output;
    private $ignoreError = false;
    private $isSession = false;
    private $sessionCommands;

    public function __construct($config, $output) {
        $this->output = $output;

        $this->ssh = new \Net_SSH2($config->hostname, $config->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($config->keyfile));

        if (!$this->ssh->login($config->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            throw new \RuntimeException("Error: Authentication failed");
        }
    }

    public function getSSHConnection() {
        return $this->ssh;
    }

    public function ignoreError() {
        $this->ignoreError = true;
        return $this;
    }

    public function execute() {
        if ($this->isSession) {
            $this->isSession = false;
            $response = $this->exec(implode(' && ', $this->sessionCommands));
            $this->sessionCommands = [];
            return $response;
        }
        else {
            throw new \RuntimeException("Error: Need to start a terminal session before using executeSession");
        }
    }

    public function add($command) {
        if ($this->isSession) {
            $this->sessionCommands[] = $command;
        }
        else {
            $this->sessionCommands = [$command];
            $this->isSession = true;
        }
        return $this;
    }

    public function run($command) {
        return $this->exec($command);
    }

    private function exec($command) {
        $response = $this->ssh->exec($command);

        if ($this->ssh->getExitStatus() != 0) {
            if($this->ignoreError) {
                $this->output->writeln('<comment>Warning! Unexpected response: '. trim($response) .'</comment>');
            }
            else {
                $message = 'Error! Unexpected response: '. trim($response);
                $this->output->writeln('<error>'. $message .'</error>');
                throw new \RuntimeException($message);
            }
        }

        $this->ignoreError = false;
        return $response;
    }
}
