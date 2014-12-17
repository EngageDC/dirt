<?php

namespace Dirt\Tools;

class MySQLBuilder {

    private $username;
    private $password;

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    public function query($query) {
        return "mysql -u" . $this->username . " -p" . $this->password . " -e \"" . $query ."\"";
    }
}
