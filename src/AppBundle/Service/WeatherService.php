<?php

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class WeatherService implements ContainerAwareInterface
{

    protected $redis;
    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->redis = $this->container->get('snc_redis.default');
    }

    public function refreshCache()
    {
        $url = $this->getUrl();

        $data = file_get_contents($url);

        $this->redis->set('weather', $data);
    }

    public function getUrl()
    {
        $config = $this->container->getParameter('weather');

        $url = implode('', [
            'https://api.darksky.net/forecast/',
            $config['api_key'],
            '/',
            $config['location'],
            '?',
            http_build_query([
                'units' => 'uk2',
            ]),
        ]);

        return $url;
    }

    public function getWeather()
    {
        $data = json_decode($this->redis->get('weather'), true);
        $rains = [];
        
        // Create an array of all precipitation events from our hourly buckets
        foreach ($data['hourly']['data'] as $hour) {
            
            $probability = isset($hour['precipProbability']) ? $hour['precipProbability'] : 0;
            $intensity = isset($hour['precipIntensity']) ? $hour['precipIntensity'] : 0;
            $type = (isset($hour['precipType']) ? $hour['precipType'] : 'rain');
            
            if ($probability < 0.1) {
                continue;
            }
            
            
            $rains[$hour['time']] = implode(' ', [
                $this->getLikelihoodWord($probability),
                $this->getIntensityWord($intensity),
                $type,
            ]);
        }

        if (empty($rains)) {
            return [];
        }

        ksort($rains);

        date_default_timezone_set('Europe/London');

        $output = [];

        $first = null;
        $last = null;
        $chain = null;
        // Batch precipitation events where there are contiguous blocks of the same intensity/likelihood
        foreach ($rains as $when => $what) {
//            echo $when . ':' . $what . ' - ';
            if ( ! $first) {
//                echo ' first ' . $when . ':' . $what . '<br/>';
                $first = $when;
                $chain = $what;
            } else if ($chain === $what && ($when === ($last + 3600) || $when === ($first + 3600))) {
//                echo ' extend ' . $when . ':' . $what . '<br/>';
                $last = $when;
            } else {
                if (!$last) {
                    $last = $first;
                }
                $output[] = [
                    'start'      => $first,
                    'end'        => $last + 3600,
                    'continuing' => ($last === ($when - 3600)),
                    'text'       => $chain,
                    'urgent'     => $first < (time() + 86400), // less than 24 hours
                ];
                $first = $when;
                $chain = $what;
                $last = null;
            }
        }
        if ($first) {
            if ( ! $last) {
                $last = $first;
            }
            $output[] = [
                'start'      => $first,
                'end'        => $last + 3600,
                'continuing' => $last > (time() + 165600), // more than 46 hours
                'text'       => $chain,
                'urgent'     => $first < (time() + 86400), // less than 24 hours
            ];
        }
        return $output;
    }
    
    protected function getLikelihoodWord($probability)
    {
        $likelihood = 'No';
        if ($probability < 0.4) {
            $likelihood = 'Unlikely';
        } else if ($probability < 0.6) {
            $likelihood = 'Likely';
        } else if ($probability < 0.8) {
            $likelihood = 'Very likely';
        } else {
            $likelihood = 'Definitely';
        }
        return $likelihood;
    }

    protected function getIntensityWord($value)
    {
        $intensity = 'light';
        if ($value > 0.3) {
            $intensity = 'medium';
        } else if ($value > 0.6) {
            $intensity = 'heavy';
        } else if ($value > 0.8) {
            $intensity = 'monstrous';
        }
        return $intensity;
    }
    
}
