<?php

namespace App\Controller;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class CommandsController extends AbstractController
{
    /**
     * @Route("public/runcommand/clear")
     */
    public function clearSymfony(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        if (function_exists('exec')) {
            echo "exec is enabled";
        } else {
            echo "exec is not enabled";
        }

        $command = 'php ../bin/console doctrine:cache:clear-metadata';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );

        $command = 'php ../bin/console doctrine:cache:clear-query';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );

        $command = 'php ../bin/console doctrine:cache:clear-result';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );
        return new JsonResponse($responseArray, 200, array());
    }

    /**
     * @Route("public/runcommand/phpmemory")
     */
    public function checkPHPMemory(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        $command = 'php -i | grep "memory_limit"';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );

        return new JsonResponse($responseArray, 200, array());
    }

    /**
     * @Route("public/runcommand/gitversion")
     */
    public function gitVersion(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        $logger->info("Server name: " . $_SERVER['SERVER_NAME']);
        $command = 'git --version';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );

        return new JsonResponse($responseArray, 200, array());
    }

    /**
     * @Route("public/runcommand/gitpull")
     */
    public function gitPull(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        try {
            $command = 'git config --global user.email nkosi.benedict@gmail.com';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );

            $command = 'git config --global user.name nkosibenedict';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );


            $command = 'git stash';
            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );

            $command = 'git pull https://github.com/benedictnkosi/agri_prices.git main --force';

            $result = $this->execute($command);
            $responseArray[] = array(
                'command' => $command,
                'result_message_auto' => print_r($result, true),
                'result_code' => 0
            );
            return new JsonResponse($responseArray, 200, array());
        } catch (Exception $ex) {
            $logger->error($ex->getMessage() . ' - ' . __METHOD__ . ':' . $ex->getLine() . ' ' . $ex->getTraceAsString());
        }
        return new JsonResponse($responseArray, 200, array());
    }

    /**
     * @Route("public/runcommand/gitstash")
     */
    public function gitStash(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        $responseArray = array();
        $command = 'git stash';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message_auto' => print_r($result, true),
            'result_code' => 0
        );

        return new JsonResponse($responseArray, 200, array());
    }


    /**
     * @Route("public/runcommand/phpinfo")
     */
    public function phpinfo(LoggerInterface $logger): Response
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }
        ob_start();
        phpinfo();
        $str = ob_get_contents();
        ob_get_clean();

        return new Response($str);
    }

    /**
     * @Route("public/runcommand/mysqldump")
     */
    public function mysql(LoggerInterface $logger): Response
    {
        $logger->info("Starting Method: " . __METHOD__);
        $command = 'mysql --version';
        $result = $this->execute($command);
        $responseArray[] = array(
            'command' => $command,
            'result_message' => print_r($result, true),
            'result_code' => 0
        );

        return new JsonResponse($responseArray, 200, array());
    }

    /**
     * Executes a command and reurns an array with exit code, stdout and stderr content
     * @param string $cmd - Command to execute
     * @param string|null $workdir - Default working directory
     * @return string[] - Array with keys: 'code' - exit code, 'out' - stdout, 'err' - stderr
     */
    function execute($cmd, $workdir = null)
    {

        if (is_null($workdir)) {
            $workdir = __DIR__;
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w"),  // stderr
        );

        $process = proc_open($cmd, $descriptorspec, $pipes, $workdir, null);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'out' => trim($stdout),
            'err' => trim($stderr),
        ];
    }


}