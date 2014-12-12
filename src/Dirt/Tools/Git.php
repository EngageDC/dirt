<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class Git {

  private $process;
  private $directory;
  private $verbose;

  public function __construct($directory, $verbose = false) {
    $this->process = new Process(null, $directory);
    $this->process->setTimeout(3600);
    $this->directory = $directory;
    $this->verbose = $verbose;
  }

  public function checkout($branchName, $exitOnError = true) {
    return $this->run("git checkout " . $branchName, $exitOnError);
  }

  public function branch($branchName, $exitOnError = true) {
    return $this->run("git branch " . $branchName, $exitOnError);
  }

  public function merge($branchName, $exitOnError = true) {
    return $this->run("git merge " . $branchName, $exitOnError);
  }

  public function push($branchName, $exitOnError = true) {
    return $this->run("git push " . $branchName, $exitOnError);
  }

  public function fetch($branchName, $exitOnError = true) {
    return $this->run("git fetch " . $branchName, $exitOnError);
  }

  public function status($exitOnError = true) {
    return $this->run("git status", $exitOnError);
  }

  public function diff($exitOnError = true) {
    return $this->run("git diff", $exitOnError);
  }

  public function add($fileName, $exitOnError = true) {
    return $this->run("git add " . $fileName, $exitOnError);
  }

  public function commit($message, $exitOnError = true) {
    return $this->run("git commit -am " . escapeshellarg($message), $exitOnError);
  }

  public function reset($branchName, $exitOnError = true) {
    return $this->run("git reset " . $branchName, $exitOnError);
  }

  private function run($command, $exitOnError = true) {

    $this->process->setCommandLine($command);
    $this->process->run();

    if ($this->verbose) {
      $this->output->write($this->process->getOutput());
    }

    if (!$this->process->isSuccessful()) {

      $this->output->writeln('<error>Error: Could not run "'.
          $this->process->getCommandLine() .'", git returned: ' .
          trim($this->process->getErrorOutput()) . '</error>');

      if ($exitOnError) {
        exit(1);
      }
      else {
          return "";
      }
    }
    else {
      return $this->process->getOutput();
    }
  }
}
