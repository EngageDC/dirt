<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class MySQLRunner {

  private $terminal;
  private $username;
  private $password;
  private $exitOnError = true;

  public function __construct(TerminalInterface $terminal, $username, $password) {
    $this->terminal = $terminal;
    $this->username = $username;
    $this->password = $password;
  }

  public function query($query) {
    return $this->terminal->run("mysql -u " . $this->username .
                                     " -p " . $this->password .
                                     " -e \"" . $query ."\"");
  }

  public function ignoreError() {
    $exitOnError = false;
    $this->terminal->ignoreError();
    return $this;
  }
}
