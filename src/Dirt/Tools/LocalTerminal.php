<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class LocalTerminal {

    private $process;
    private $directory;
    private $output;
    private $ignoreError = false;

    public function __construct($directory, $output) {
        $this->process = new Process(null, $directory);
        $this->process->setTimeout(3600);
        $this->directory = $directory;
        $this->output = $output;
    }

    public function ignoreError() {
        $this->ignoreError = true;
        return $this;
    }

    public function run($command) {
        $this->process->setCommandLine($command);
        $this->process->run();

        if (!$this->process->isSuccessful()) {
            $message = 'Could not run "'. $this->process->getCommandLine()
            .'", command returned: ' . trim($this->process->getErrorOutput());

            if($this->ignoreError) {
                $this->output->writeln('<comment>Warning: '. $message .'</comment>');
            }
            else {
                $this->output->writeln('<error> Error: '. $message .'</error>');
                throw new \RuntimeException($message);
            }
        }

        $this->ignoreError = false;
        return $this->process->getOutput();
    }
}
