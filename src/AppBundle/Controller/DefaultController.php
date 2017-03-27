<?php

namespace AppBundle\Controller;

use AppBundle\Alexa\Request\CachedCertificate;
use AppBundle\Alexa\Skill\HomeSkill as HomeSkill;
use AppBundle\Service\NetworkPresenceService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }
    
    /**
     * @Route("/test", name="testpage")
     */
    public function testAction(Request $request)
    {
        $network = $this->container->get('network_presence');
        $known = $network->getKnownPeople();
        
        foreach ($known as $key => $person) {
            $known[$key] = [
                'onNetwork' => $network->isPersonOnNetwork($person['names'][0]),
                'names'     => implode(', ', $person['names']),
                'address'   => $person['address'],
            ];
        }
        
        $atHome = $network->getPeopleAtHome(NetworkPresenceService::OUTPUT_NAME_PRIMARY);
        
        $arpLog = $network->getArpLogs();
        $hourMarkers = [];
        foreach ($arpLog as $mac => $logs) {
            foreach ($logs as $time => $available) {
                if (preg_match('/ \d\d\:00\:/', $time)) {
                    $hourMarkers[] = true;
                } else {
                    $hourMarkers[] = false;
                }
            }
            break;
        }
        
        // replace this example code with whatever you need
        return $this->render('default/test.html.twig', [
            'known'       => $known,
            'atHome'      => $atHome,
            'arpLog'      => $arpLog,
            'hourMarkers' => $hourMarkers,
        ]);
    }
    
    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboardAction(Request $request)
    {
        $network = $this->container->get('network_presence');
        $known = $network->getKnownPeople();
        $atHome = $network->getPeopleAtHome(NetworkPresenceService::OUTPUT_KEY);
        $knownPeople = [];
        foreach ($known as $key => $person) {
            $knownPeople[] = [
                'key'       => $key,
                'name'      => $person['names'][0],
                'isVisible' => in_array($key, $atHome),
            ];
        }
        
        $weather = $this->container->get('weather')->getWeather();
        
        return $this->render('default/dashboard.html.twig', [
            'when'        => date('H:i:s'),
            'knownPeople' => $knownPeople,
            'power'       => $this->runPlugCommand('{"emeter":{"get_realtime":{}}}'),
            'weather'     => $weather,
        ]);
    }
    
    protected function runPlugCommand($json)
    {
        $command = implode(' ', [
            $this->container->getParameter('kernel.root_dir') . '/../bin/tplink-smartplug.py',
            '-t',
            $this->container->getParameter('tp_link_plug_ip'),
            '-j',
            "'" . $json . "'",
        ]);
        
        $process = new Process($command);
        $process->run();

        // executes after the command finishes
        if ( ! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
                
        return json_decode($output, true);
    }

}
