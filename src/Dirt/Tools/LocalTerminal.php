<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class LocalTerminal implements TerminalInterface {

  private $process;
  private $directory;
  private $output;
  private $exitOnError = true;

  public function __construct($directory, $output) {
    $this->process = new Process(null, $directory);
    $this->process->setTimeout(3600);
    $this->directory = $directory;
    $this->output = $output;
  }

  public function ignoreError() {
    $this->exitOnError = false;
    return $this;
  }

  public function run($command) {
    $this->process->setCommandLine($command);
    $this->process->run();

    if (!$this->process->isSuccessful()) {
      $this->output->writeln('<error>Error: Could not run "'.
        $this->process->getCommandLine() .'", command returned: ' .
        trim($this->process->getErrorOutput()) . '</error>');

      if($this->exitOnError) {
        exit(1);
      }
    }

    $this->exitOnError = true;
    return $this->process->getOutput();
  }
}
