<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class BasePlugCommand extends BaseCommand
{
    protected $commandPath;
    protected $target = '';
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->commandPath = $this->getContainer()->getParameter('kernel.root_dir') . '/../bin/tplink-smartplug.py';
        
        $this->target = $input->getOption('target');
    }
    
    protected function runPlugCommand($json)
    {
        $command = implode(' ', [
            $this->commandPath,
            '-t',
            $this->target,
            '-j',
            "'" . $json . "'",
        ]);
        
        $this->writeln('Running on plug ' . $command);
        
        $process = new Process($command);
        $process->run();

        // executes after the command finishes
        if ( ! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        
        $this->writeln('Output from plug ' . $output);
        
        return json_decode($output, true);
    }
}