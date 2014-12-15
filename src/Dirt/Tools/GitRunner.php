<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class GitRunner {

  private $terminal;
  private $exitOnError = true;

  public function __construct(TerminalInterface $terminal) {
    $this->terminal = $terminal;
  }

  public function __call($name, $arguments) {
    return $this->terminal->run('git ' . $name . ' ' . implode(' ', $arguments));
  }

  public function commit($message) {
    return $this->terminal->run("git commit -am " . escapeshellarg($message));
  }

  public function ignoreError() {
    $exitOnError = false;
    $this->terminal->ignoreError();
    return $this;
  }
}
