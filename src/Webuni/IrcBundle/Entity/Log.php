<?php

namespace Webuni\IrcBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="irc_log")
 */
class Log
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(name="nick", type="string", length=32)
     */
    protected $nick;

    /**
     * @ORM\Column(name="text", type="text")
     */
    protected $text;

    /**
     * @ORM\Column(name="command", type="string", length=8)
     */
    protected $command;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $datetime;

    /**
     * @ORM\ManyToOne(targetEntity="Channel", cascade={"persist"})
     */
    protected $channel;

    public function __construct($nick, $command, $text, $time = null, Channel $channel = null)
    {
        $this->nick = $nick;
        $this->command = $command;
        $this->datetime = null ?: new \DateTime();
        $this->channel = $channel;
        $this->setText($text);
    }

    public function setText($text)
    {
        $encoding = mb_detect_encoding($text, 'UTF-8,ISO-8859-1,ISO-8859-2', true) ?: 'ISO-8859-1';
        if ('UTF-8' !== $encoding) {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        $this->text = $text;
    }
}
