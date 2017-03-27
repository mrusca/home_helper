<?php
namespace AppBundle\Alexa\Request;

use Alexa\Request\Certificate;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CachedCertificate extends Certificate
{
    const CERTIFICATE_CACHE_KEY = 'alexa-certificate';
    const CERTIFICATE_CACHE_TTL = 600;
    
    protected $container;
    protected $redis;
    protected $logger;
    
    public static function createDefault(ContainerInterface $container)
    {
        if ( ! (array_key_exists('HTTP_SIGNATURECERTCHAINURL', $_SERVER) 
                && array_key_exists('HTTP_SIGNATURE', $_SERVER))) {
            throw new Exception('Request not from Amazon');
        }
        return new static($container, $_SERVER['HTTP_SIGNATURECERTCHAINURL'], $_SERVER['HTTP_SIGNATURE']);
    }
    
  	public function __construct(ContainerInterface $container, $certificateUrl, $signature) 
    {
		$this->certificateUrl = $certificateUrl;
		$this->requestSignature = $signature;
        $this->container = $container;
        $this->redis = $this->container->get('snc_redis.default');
        $this->logger = $this->container->get('logger');
	}
    
    public function getCertificate()
    {
        $cert = $this->redis->get(static::CERTIFICATE_CACHE_KEY);
        $this->logger->info(__METHOD__ . ' Have cached certificate ' . var_export(boolval($cert), true));
        if (empty($cert)) {
            $cert = $this->fetchCertificate();
            $this->redis->set(static::CERTIFICATE_CACHE_KEY, $cert, 'ex', static::CERTIFICATE_CACHE_TTL);
        }
        return $cert;
    }

}
