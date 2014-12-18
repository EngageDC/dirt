<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class RemoteFileSystem {

    private $sftp;
    private $output;
    private $ignoreError = false;

    public function __construct($config, $output) {
        $this->output = $output;

        $this->ssh = new \Net_SFTP($config->hostname, $config->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($config->keyfile));

        if (!$this->ssh->login($config->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            throw new \RuntimeException("Error: Authentication failed");
        }
    }

    public function upload($destination, $file) {
        $sftp->put($destination, $file, NET_SFTP_LOCAL_FILE);
    }
}
