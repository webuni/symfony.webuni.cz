<?php

namespace Webuni\IrcBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="irc_channel")
 */
class Channel
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(name="server", type="string", length=64)
     */
    protected $server;

    /**
     * @ORM\Column(name="name", type="string", length=32)
     */
    protected $name;

    public function __construct($name, $server)
    {
        $this->name = $name;
        $this->server = $server;
    }
}
