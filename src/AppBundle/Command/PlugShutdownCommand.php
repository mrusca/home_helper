<?php
namespace AppBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PlugShutdownCommand extends BasePlugCommand
{
    protected $commandPath;
    protected $target = '';
    
    protected function configure()
    {
        $this
            ->setName('plug:shutdown')
            ->setDescription('Shuts down a TP Link plug')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_OPTIONAL,
                'The IP address of the plug',
                '192.168.0.0'
            )
            ->addOption(
                'safe',
                null,
                InputOption::VALUE_OPTIONAL,
                'Wait until the power draw drops below this level',
                '49'
            )
            ->addOption(
                'wait',
                null,
                InputOption::VALUE_OPTIONAL,
                'Wait at least this many seconds before starting',
                '0'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        
        $this->target = $input->getOption('target');
        $this->writeStart('Shutting down TP Link plug ' . $this->target);
        
        $wait = $input->getOption('wait');
        $wait = intval($wait);
        
        if ($wait > 0) {
            $this->writeln('Waiting ' . $wait . ' seconds first');
            sleep($wait);
        }
        
        $safe = $input->getOption('safe');
        $safe = intval($safe);
        if ($safe > 0) {
            $this->writeln('Safe shutdown - waiting until power drops below ' . $safe);
        } else {
            $this->writeln('Unsafe shutdown - no waiting');
        }
        
        if ($this->waitForPower($safe)) {
            $this->turnOff();
        } else {
            $this->writeln('We waited ages, but the power is not dropping. Exiting');
        }
        
        $this->writeFinish();
    }
    
    protected function waitForPower($waitFor)
    {
        $maxIterations = 200;
        $iteration = 0;
        $interval = 3;
        $power = 1000000;
        $json = '{"emeter":{"get_realtime":{}}}';
        do {
            if ($iteration > 0) {
                sleep($interval);
            }
            
            if (($iteration % 20) === 0) {
                $this->writeln('Waiting for power output to reach ' . $waitFor . '. Last reading ' . $power);
            }
            
            $reading = $this->runPlugCommand($json);
            //$this->writeln(var_export($reading, true));
            $power = $reading['emeter']['get_realtime']['power'];
            $iteration++;
        } while (($power >= $waitFor) && ($iteration < $maxIterations));
        
        if ($power < $waitFor) {
            return true;
        }
        return false;
    }
    
    protected function turnOff()
    {
        $this->writeln('Turning off the plug');
        $this->runPlugCommand('{"system":{"set_relay_state":{"state":0}}}');
    }
}