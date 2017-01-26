<?php
namespace Dirt\Deployer;

use Symfony\Component\Process\Process;
use Dirt\Configuration;

class WPEngineDeployer extends Deployer
{
    private $stagingSSH;
    private $productionSSH;
    private $stagingSFTP;
    private $productionSFTP;
    private $databaseCredentials;

    /**
     * Returns current environment name
     * @return string
     */
    public function getEnvironment()
    {
        return 'wpengine';
    }

    /**
     * Invokes the deployment process, pushing latest version to the staging server
     */
    public function deploy()
    {
        if (!self::command_exists('git-ftp')) {
             $this->output->writeln('<error>You must have git-ftp installed to deploy to WPEngine. Visit https://github.com/git-ftp/git-ftp/blob/develop/INSTALL.md#mac-os-x for more information.</error>');
             return;
        }
        $wpengine = $this->project->getWpConfig();
        if (empty($wpengine)) {
            $this->output->writeln('<info>GIT-FTP: WPEngine is not configured.  We will need to ask you for some information.</info>');
            $wp_config = [];

            $options = ['FTP_USER'=>'','FTP_PASSWORD'=>'','FTP_URL'=>'','FTP_PROTOCOL'=>'sftp','FTP_PORT'=>'2222','FTP_REMOTE_FOLDER'=>'wp-content','LOCAL_DIRECTORY_TO_SYNC'=>'public/wp-content'];

            foreach ($options as $field=>$default) {
                if ($default!='') {
                    $question = '<question>' . $field . ' (Leave empty for: ' . $default . '): </question>';
                } else {
                  $question = '<question>' . $field . ': </question>';
                }
                $wp_config[$field] = $this->dialog->askAndValidate(
                    $this->output,
                    $question,
                    function ($answer) {
                        if (empty($answer) && empty($default)) {
                            throw new \RuntimeException(
                                'You must enter ' . $field
                            );
                        }

                        return $answer;
                    },
                    false,
                    $default
                );
            }

            $this->project->setWpConfig($wp_config);
            $this->project->save();
            $wpengine = $this->project->getWpConfig();

        }

        $command = 'git ftp init --user ' . $wpengine->FTP_USER . ' --passwd ' . $wpengine->FTP_PASSWORD . ' ' . $wpengine->FTP_PROTOCOL .'://' . $wpengine->FTP_URL . ':' . $wpengine->FTP_PORT . '/' . $wpengine->FTP_REMOTE_FOLDER . ' --syncroot ' . $wpengine->LOCAL_DIRECTORY_TO_SYNC;

        $this->output->writeln('<comment>GIT-FTP: Initializing and deploying (Will fail if already initialized)</comment>');
        exec($command, $exec_output, $return) ;
        if ($return==0) {
            //init and deploy successful
            $this->output->writeln('<info>GIT-FTP: Initialization completed</info>');
            $this->output->writeln('<info>GIT-FTP: Deployment completed</info>');
        } else if ($return==2) {
            //already init, let's just deploy
            $this->output->writeln('<comment>GIT-FTP: Already initialized, beginning deployment</comment>');
            $command = str_replace('init','push',$command);
            exec($command, $exec_output, $return) ;
            if ($return==0) {
                //deploy success
                $this->output->writeln('<info>GIT-FTP: Deployment complete</info>');
            } else {
                //deploy failed
                $this->error ($return);
            }
        } else {
            //init failed
            $this->error ($return);
        }

    }

    private static function command_exists($cmd) {
        $returnVal = shell_exec("which $cmd");
        return (empty($returnVal) ? false : true);
    }

    private function error($status) {
        $error = '';
            switch ($status) {
                case 1 :
                    $error = 'Unknown error';
                    break;
                case 2 :
                    $error = 'Wrong Usage';
                    break;
                case 3 :
                    $error = 'Missing arguments';
                    break;
                case 4 :
                    $error = 'Error while uploading';
                    break;
                case 5 :
                    $error = 'Error while downloading';
                    break;
                case 6 :
                    $error = 'Unknown protocol';
                    break;
                case 7 :
                    $error = 'Remote locked';
                    break;
                case 8 :
                    $error = 'Not a Git project';
                    break;
                default :
                     $error = 'Unknown error';
            }

            $this->output->writeln('<error>GIT-FTP: Deployment failed: ' . $error . '</error>');
    }

    /**
     * Removes all configuration, files and database credentials from the production server
     */
    public function undeploy()
    {
        $this->output->writeln('This feature is not available for production deployment');
    }

}