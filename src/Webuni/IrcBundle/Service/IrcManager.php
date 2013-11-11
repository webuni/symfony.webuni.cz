<?php

namespace Webuni\IrcBundle\Service;

use Doctrine\Common\Persistence\ObjectManager;
use Webuni\IrcBundle\Entity\Channel;

class IrcManager
{
    private $om;
    private $channelClass = 'Webuni\IrcBundle\Entity\Channel';
    private $logClass = 'Webuni\IrcBundle\Entity\Log';

    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

    public function getChannel($name, $server, $create = true)
    {
        $name = '#' === $name[0] ? substr($name, 1) : $name;
        $channel = $this->om->getRepository($this->channelClass)->findOneBy(array('name' => $name, 'server' => $server));

        if (null === $channel && $create) {
            $channel = new $this->channelClass($name, $server);
        }

        return $channel;
    }

    public function getObjectManager()
    {
        return $this->om;
    }

    public function log(Channel $channel, $nick, $command, $message, $time = null)
    {
        $log = new $this->logClass($nick, $command, $message, $time, $channel);

        $this->om->persist($log);
        $this->om->flush($log);
        $this->om->clear($this->logClass);
    }
}
