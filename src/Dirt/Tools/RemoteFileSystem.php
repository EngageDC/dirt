<?php

namespace Dirt\Tools;

use Symfony\Component\Process\Process;

class RemoteFileSystem {

    private $sftp;
    private $output;
    private $ignoreError = false;

    public function __construct($config, $output) {
        $this->output = $output;

        $this->sftp = new \Net_SFTP($config->hostname, $config->port);
        $key = new \Crypt_RSA();
        $key->loadKey(file_get_contents($config->keyfile));

        if (!$this->sftp->login($config->username, $key)) {
            $this->output->writeln('<error>Error: Authentication failed</error>');
            throw new \RuntimeException("Error: Authentication failed");
        }
    }

    /**
     * Uploads a file to the remote server with the given $destination
     * @param  string $destination Full path and filename of remote file
     * @param  string $file        Full path and filename of local file
     */
    public function upload($destination, $file) {
        $this->sftp->put($destination, $file, NET_SFTP_LOCAL_FILE);
    }

    /**
     * Downloads a file from the remote server from the given $destination
     * @param  string $destination Full path and filename of remote file
     * @param  string $file        Full path and filename of local file
     */
    public function download($destination, $file) {
        $this->sftp->get($destination, $file);
    }

}
