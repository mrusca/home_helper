<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GetWeatherCommand extends BaseCommand
{    
    protected function configure()
    {
        $this
            ->setName('weather:get')
            ->setDescription('Gets weather info')
            
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->writeStart('Getting weather');
        
        $service = $this->getContainer()->get('weather');
                
        $this->writeln('URL: ' . $service->getUrl());
        
        $service->refreshLocal();
        
        $this->writeFinish();
    }

}