<?php

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class NetworkPresenceService implements ContainerAwareInterface
{
    const OUTPUT_KEY = 'key';
    const OUTPUT_NAME_PRIMARY = 'primary';
    const OUTPUT_NAME_RANDOM = 'random';
    
    protected $redis;
    protected $container;
    
    protected $knownPeople;
    
    public function getArpLogs()
    {
        $rawLogs = $this->redis->lrange('arp-log', 0, -1);
        $logs = [];
        $dates = [];
        $logCount = 0;
        foreach ($rawLogs as $log) {
            $lines = explode(PHP_EOL, $log);
            $lineNo = 0;
            $date = null;
            foreach ($lines as $line) {
                if ($lineNo > 0) {
                    $pieces = explode(' ', $line);
                    $mac = $pieces[3];
                    if ($mac == '<incomplete>') {
                        continue;
                    }
                    if ( ! array_key_exists($mac, $logs)) {
                        $logs[$mac] = array_combine($dates, array_fill(0, count($dates), false));
                    }
                    $logs[$mac][$date] = true;
                } else {
                    $date = $line;
                    $dates[] = $date;
                }
                $lineNo++;
            }
            $logCount++;
            foreach ($logs as $mac => &$events) {
                if (count($events) < $logCount) {
                    $events[] = false;
                }
            }
        }
        return $logs;
    }
    
    public function arePeopleVisible($desired)
    {
        $found = [];
        $known = $this->getKnownPeople();
        $mostRecent = $this->redis->lrange('arp-log', 0, 1);
        foreach ($desired as $personKey) {
            foreach ($mostRecent as $log) {
                if (stripos($log, $known[$personKey]['address'])) {
                    $found[] = $personKey;
                    break;
                }
            }
        }
        
        return $found;
    }
    
    public function setKnownPeople($knownPeople)
    {
        $this->knownPeople = $knownPeople;
    }
    
    public function getKnownPeople()
    {
        return $this->knownPeople;
    }
    
    public function getKeyForPerson($person)
    {
        foreach ($this->getKnownPeople() as $key => $knownPerson) {
            if (in_array($person, $knownPerson['names'])) {
                return $key;
            }
        }
        return null;
    }
    
    public function isPersonOnNetwork($person)
    {
        $key = $this->getKeyForPerson($person);
        if ( ! $key) {
            return false;
        }
        return ! empty($this->arePeopleVisible([$key]));
    }
    
    public function getPeopleAtHome($output = self::OUTPUT_NAME_PRIMARY)
    {
        $known = $this->getKnownPeople();
        $visible = $this->arePeopleVisible(array_keys($known));
        if ($output === static::OUTPUT_KEY) {
            return $visible;
        }
        $people = [];
        foreach ($visible as $key) {
            $names = $known[$key]['names'];
            if ($output === static::OUTPUT_NAME_RANDOM) {
                $people[] = $names[array_rand($names)];
            } else {
                $people[] = $names[0];
            }
        }
        return $people;
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container; 
        $this->redis = $this->container->get('snc_redis.default');
    }

}

