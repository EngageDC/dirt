<?php

namespace Dirt\Tools;

class MySQLBuilder {

    private $username;
    private $password;

    public function __construct($config) {
        $this->username = $config->username;
        $this->password = $config->password;
    }

    public function query($query) {
        return "mysql -u" . $this->username . " -p" . $this->password . " -e \"" . $query ."\"";
    }

    public function import($file, $database) {
        return "mysql -u". $this->username ." -p". $this->password ." ". $database ." < " . $file;
    }
}
