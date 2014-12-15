<?php

namespace Dirt\Tools;

interface TerminalInterface {

  public function run($command);

  public function ignoreError();

}
