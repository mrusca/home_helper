<?php

namespace AppBundle\Alexa\Skill;

use Alexa\Request\IntentRequest;
use Alexa\Request\Request as AlexaRequest;
use Alexa\Response\Response as AlexaResponse;
use AppBundle\Service\NetworkPresenceService;
use Cocur\BackgroundProcess\BackgroundProcess;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;

class HomeSkill extends BaseSkill
{
    protected $redis;
    protected $network;
    
    protected static $STRINGS = [
        'head' => [
            'I think',
            'It looks like',
            'It seems',
            'It appears that',
        ],
        'body' => [
            '%s %s at home',
            '%s %s in',
            '%s %s about',
            '%s %s around',
            '%s %s in the house',
            '%s %s here',
        ],
        'exhort' => [
            'only',
            'just',
        ],   
        'tail' => [
            'right now',
            'at the moment',
        ],
        'everyone' => [
            'everyone',
            'everybody',
            'housemates',
            'gang',
            'team',
            'friends',
        ],
        'cadbury' => [
            'cadbury',
            'cadders',
        ]
    ];
    
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->redis = $this->container->get('snc_redis.default');
        $this->network = $this->container->get('network_presence');
    }
    
    public function getApplicationId()
    {
        return $this->container->getParameter('alexa_home_skill_id');
    }

    public function handle(Request $incomingRequest)
    {
        $this->preHandle($incomingRequest);
        $this->setupCertificate();
        $request = $this->parseRequest($incomingRequest);
        
        $response = new AlexaResponse();
        $response->endSession();
        if ($request instanceof IntentRequest) {
            $this->logger->info($this->debugLabel . 'Got intent ' . $request->intentName);
            
            switch ($request->intentName) {
                case 'GetWhoIsAtHome':
                    $this->handleGetWhoIsAtHome($request, $response);
                    break;
                case 'PCStartup':
                    $this->handlePCStartup($request, $response);
                    break;
                case 'PCShutdown':
                    $this->handlePCShutdown($request, $response);
                    break;
                default:
                    $response->respond('I can\'t do that, Dave');
                    break;
            }
        } else {
            $response->respond('Dunno mate');
        }
        $this->postHandle($response);
        return $response->render();
    }
    
    protected function handleGetWhoIsAtHome(AlexaRequest &$request, AlexaResponse &$response)
    {
        $occupant = $request->getSlot('Occupant');
        $everyone = $this->isDesiredOccupantEveryone($occupant);
        $response->sessionAttributes['__occupant'] = $occupant;
        $response->sessionAttributes['__everyone'] = $everyone;
        
        
        if ($occupant && ( ! $everyone)) {
            $response->sessionAttributes['__path'] = 'a';
            if ($this->isOccupantCadbury($occupant)) {
                $message = $this->getCadburyMessage();
                $response->sessionAttributes['__cadbury'] = true;
            } else {
                $negative = ( ! $this->network->isPersonOnNetwork($occupant));
                $response->sessionAttributes['__negative'] = $negative;
                $message = $this->getPeopleAtHomeMessage([$occupant], false, true, $negative);
            }
            $response->respond($message);
        } else {
            $response->sessionAttributes['__path'] = 'b';
            $people = $this->network->getPeopleAtHome(NetworkPresenceService::OUTPUT_NAME_PRIMARY);
            if (empty($people)) {
                $response->respond('I can\'t see anyone at home right now, but you\'re asking me, so someone must be in.');
            } else {
                $response->respond($this->getPeopleAtHomeMessage($people, $everyone, $everyone, false));
            }
        }
    }
    
    protected function joinNames($names)
    {      
        $final = null;
        if (count($names) > 1) {
            $final = array_pop($names);
        }
        return implode(', ', $names) . ($final ? ' and ' . $final : '');
    }
    
    protected function getPeopleAtHomeMessage($people, $askingForEveryone = false, $canBeYesOrNo = false, 
                                              $isNegative = false)
    {
        $parts = static::$STRINGS;
        
        $isEveryone = (count($people) === count($this->network->getKnownPeople()));
        if ($isEveryone) {
            $names = $this->getUtterableForEveryone();
            $singular = true;
        } else {
            $names = $this->joinNames($people);
            $singular = (count($people) === 1);
            $isNegative = $isNegative || $askingForEveryone;
        }
        $output = '';
        
        if ($canBeYesOrNo) {
            $double = ($isNegative ? 'No' : 'Yes');
            if (rand(0, 100 < 20)) {
                $double .= ' my good friend';
            }
            $output .= $double . ', ';
        }
        
        if (rand(0, 100) < 20) {
            $output .= $this->pick($parts['head']) . ' ';
        }
        
        if (( ! $isEveryone) && $askingForEveryone && ($canBeYesOrNo || rand(0, 100) < 50)) {
            $output .= $this->pick($parts['exhort']) . ' ';
        }
        
        $verb = ($singular ? 'is' : 'are');
        if ($isNegative && ( ! $askingForEveryone)) {
            $verb = (rand(0, 100) < 50) ? 'isn\'t' : 'is not';
        }
        
        $output .= sprintf($this->pick($parts['body']), $names, $verb);        
        
        if (rand(0, 100) < 20) {
            $output .= ', ' . $this->pick($parts['tail']);
        }
        
        return $output;
    }
    
    protected function isDesiredOccupantEveryone($occupant)
    {
        $occupant = strtolower($occupant);
        foreach (static::$STRINGS['everyone'] as $test) {
            if (stripos($occupant, $test) !== false) {
                return true;
            }
        }
        return false;
    }
    
    protected function getUtterableForEveryone()
    {
        if (rand(0, 100) < 50) {
            $options = ['everyone', 'everybody'];
            return $this->pick($options);
        }
        $a = [
            'the whole',
            'the whole of the',
            'the entire',
            'the entirety of the',
            'all of the',
        ];
        $b = ['gang', 'team'];
        return $this->pick($a) . ' ' . $this->pick($b);
    }
    
    protected function isOccupantCadbury($occupant) 
    {
        return in_array(strtolower($occupant), static::$STRINGS['cadbury']);
    }
    
    protected function getCadburyMessage()
    {
        if (rand(0, 100) < 50) {
            return $this->getPeopleAtHomeMessage(['cadbury'], false, true, false);
        } 
        $options = [
            'Cadbury is always here',
            'Cadbury is just upstairs sleeping',
            'Cadbury has gone out for walkies and will be back in a minute',
            'Cadbury lives in our hearts',
        ];
        return $this->pick($options);
    }
    
    protected function pick(array $arr)
    {
        return $arr[array_rand($arr)];
    }
    

    protected function handlePCStartup(AlexaRequest &$request, AlexaResponse &$response)
    {
        $rootDir = $this->container->getParameter('kernel.root_dir');
        $binPath = $rootDir . '/../bin/';
        $ip = $this->container->getParameter('tp_link_plug_ip');
        
        $commands = [
            'php ' . $binPath . 'console plug:startup --target=' . $ip,
            $binPath . 'pc-startup.sh',
        ];
        
        $process = new BackgroundProcess(implode('; ', $commands));
        $process->run($rootDir . '/../var/logs/bg_proc.log');

        $response->respond('Initiated');
    }
    
    protected function handlePCShutdown(AlexaRequest &$request, AlexaResponse &$response)
    {
        $rootDir = $this->container->getParameter('kernel.root_dir');
        $binPath = $rootDir . '/../bin/';
        $ip = $this->container->getParameter('tp_link_plug_ip');
        
        $commands = [
            $binPath . 'pc-shutdown.sh',
            'php ' . $binPath . 'console plug:shutdown --wait=9 --target=' . $ip,
        ];
        
        $process = new BackgroundProcess(implode('; ', $commands));
        $process->run($rootDir . '/../var/logs/bg_proc.log');

        $response->respond('Shutting down');
        
        
    }
}

