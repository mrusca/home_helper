<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends ContainerAwareCommand
{
    protected $input;
    protected $output;
    protected $outputLevel = 0;
    protected $logger;
    protected $debugLabel = '[BaseCommand] ';
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->logger = $this->getContainer()->get('logger');
        $reflect = new \ReflectionClass($this);
        $this->debugLabel = '[' . $reflect->getShortName() . '] ';
    }
    
    protected function indent()
    {
        $this->outputLevel++;
    }
    
    protected function outdent()
    {
        $this->outputLevel--;
    }
    
    protected function writeln($message)
    {
        $prefix = ($this->outputLevel ? str_repeat('  ', $this->outputLevel) . ' - ' : '');
        $this->output->writeln($prefix . $message);
        $this->logger->debug($this->debugLabel . $message);
    }
    
    protected function writeStart($message)
    {
        $this->output->writeln('█ ' . $message);
        $this->logger->debug($this->debugLabel . '█ ' . $message);
        $this->indent();
    }
    
    protected function writeFinish($message = 'Finished')
    {
        $this->output->writeln('# ' . $message);
        $this->logger->debug($this->debugLabel . '# ' . $message);
        $this->outputLevel = 0;
    }
}
