<?php

namespace AppBundle\Alexa\Skill;

use Alexa\Request\IntentRequest;
use Alexa\Request\Request as AlexaRequest;
use Alexa\Response\Response as AlexaResponse;
use AppBundle\Alexa\Request\CachedCertificate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseSkill
{
    protected $container;
    protected $logger;
    protected $alexaCertificate;
    protected $debugLabel = '[BaseSkill] ';
    
    public abstract function getApplicationId();
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $reflect = new \ReflectionClass($this);
        $this->debugLabel = '[' . $reflect->getShortName() . '] ';
    } 
    
    protected function parseRequest(Request $request)
    {
        $rawRequest = $request->getContent();
        $alexaRequest = new AlexaRequest($rawRequest, $this->getApplicationId());
        $alexaRequest->setCertificateDependency($this->alexaCertificate);
        return $alexaRequest->fromData();
    }
    
    protected function setupCertificate()
    {
        $this->alexaCertificate = CachedCertificate::createDefault($this->container);
    }
    
    protected function preHandle(Request &$incomingRequest)
    {
        $this->startTime = microtime(true);
    }
    
    protected function postHandle(AlexaResponse &$response)
    {
        $response->sessionAttributes['__time'] = (microtime(true) - $this->startTime);
    }
    
    public function handle(Request $incomingRequest) 
    {
        $incomingRequest = $this->preHandle($incomingRequest);
        $this->setupCertificate();
        $request = $this->parseRequest($incomingRequest);
        
        $response = new AlexaResponse();
        $response->endSession();
        $response->respond('Base Skill says hi');
        $response = $this->postHandle($response);
        return $response->render();
    }
}
