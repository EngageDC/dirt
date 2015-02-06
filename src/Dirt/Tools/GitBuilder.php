<?php

namespace Dirt\Tools;

class GitBuilder {

    public function __call($name, $arguments) {
        return 'git ' . $name . ' ' . implode(' ', $arguments);
    }

    public function commit($message) {
        return "git commit -am " . escapeshellarg($message);
    }
}
