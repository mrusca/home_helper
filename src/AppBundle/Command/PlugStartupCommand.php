<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PlugStartupCommand extends BasePlugCommand
{
    protected $commandPath;
    protected $target = '';
    
    protected function configure()
    {
        $this
            ->setName('plug:startup')
            ->setDescription('Starts up a TP Link plug')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_OPTIONAL,
                'The IP address of the plug',
                '192.168.0.0'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->target = $input->getOption('target');
        $this->writeStart('Turning on TP Link plug ' . $this->target);

        $this->turnOn();
        
        $this->writeFinish();
    }
    
    protected function turnOn()
    {
        $this->writeln('Turning on the plug');
        $this->runPlugCommand('{"system":{"set_relay_state":{"state":1}}}');
    }
}