<?php
namespace Webuni\IrcBundle\Command;

use Doctrine\ORM\EntityManager;
use Phergie\Irc\Client\React\Client;
use Phergie\Irc\Connection;
use React\EventLoop\Timer\TimerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IrcBotCommand extends ContainerAwareCommand
{
    private $writer;
    private $manager;
    private $nick;
    private $channels = array();
    private $users = array();

    protected function configure()
    {
        $this
            ->setName('irc:bot')
            ->setDescription('Launches the irc bot for channels')
            ->addArgument('nick', InputArgument::REQUIRED, 'The user\'s nickname')
            ->addArgument('server', InputArgument::OPTIONAL, 'The hostname of the server', 'irc.freenode.net')
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'The user\'s username')
            ->addOption('realname', 'r', InputOption::VALUE_OPTIONAL, 'The user\'s realname')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port on which the server is running', 6667)
            ->addOption('pingtime', 'pt', InputOption::VALUE_OPTIONAL, 'The time interval in seconds between ping commands', 120)
            ->addOption('channel', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The channels the bot should join')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $server = $input->getArgument('server');
        $this->nick = $input->getArgument('nick');

        $connection = new Connection();
        $connection->setServerHostname($input->getArgument('server'));
        $connection->setServerPort($input->getOption('port'));
        $connection->setNickname($this->nick);
        $connection->setUsername($input->getOption('username') ?: $this->nick);
        $connection->setRealname($input->getOption('realname') ?: $this->nick);

        $this->manager = $this->getContainer()->get('webuni_irc.manager');

        if (($om = $this->manager->getObjectManager()) instanceof EntityManager) {
            $om->getConnection()->getConfiguration()->setSQLLogger(null);
        }

        foreach ($input->getOption('channel') as $channel) {
            $key = 0 === strpos(trim($channel), '#') ? trim($channel) : '#'.trim($channel);
            $this->channels[$key] = $this->manager->getChannel($key, $server);
        }

        $client = new Client();
        $client->on('irc.received', array($this, 'joinListener'));
        $client->on('irc.received', array($this, 'pongListener'));
        $client->on('irc.received', array($this, 'namesListener'));
        $client->on('irc.received', array($this, 'messageListener'));
        $client->addPeriodicTimer($input->getOption('pingtime'), array($this, 'pingTimer'));
        $client->run($connection);
    }

    public function joinListener($message, $write)
    {
        switch ($message['command']) {
            case 376: // RPL_ENDOFMOTD
            case 422: // ERR_NOMOTD
                $this->writer = $write;
                $write->ircJoin(implode(',', array_keys($this->channels)));
                break;
        }
    }

    public function pongListener($message, $write)
    {
        if ('PING' == $message['command']) {
            $write->ircPong($message['params']['server1'], isset($message['params']['server2']) ? $message['params']['server2'] : null);
        }
    }

    public function pingTimer()
    {
        if ($this->writer) {
            $this->writer->ircPing('');
        }
    }

    public function namesListener($message, $write)
    {
        $command = $message['command'];
        $nick = isset($message['nick']) ? $message['nick']: null;

        if (353 == $command) {
            // RPL_NAMREPLY
            $channel = $message['params'][3];
            foreach (explode(' ', str_replace(' @', ' ', $message['params'][4])) as $user) {
                $this->users[$user][$channel] = $channel;
            }
        } elseif ('JOIN' == $command) {
            foreach ($message['targets'] as $channel) {
                if ($this->nick == $nick) {
                    $write->ircNames($channel);
                } else {
                    $this->users[$nick][$channel] = $channel;
                }
            }
        } elseif ('PART' == $command) {
            foreach ($message['targets'] as $channel) {
                if (isset($this->users[$nick][$channel])) {
                    unset($this->users[$nick][$channel]);
                }
            }
            if (isset($this->users[$nick]) && empty($this->users[$nick])) {
                unset($this->users[$nick]);
            }
        }
    }

    public function messageListener($message)
    {
        if (in_array($command = $message['command'], array('JOIN', 'PRIVMSG', 'PART', 'QUIT'))) {
            $nick = $message['nick'];
            if ($this->nick == $nick) {
                return;
            }

            $channels = array();
            if (isset($message['targets'])) {
                $channels = $message['targets'];
            } elseif (isset($this->users[$nick])) {
                $channels = $this->users[$nick];
            }

            $text = '';
            if (isset($message['params']['text'])) {
                $text = $message['params']['text'];
            } elseif ('JOIN' == $command) {
                $text = isset($message['host']) ? $message['user'].'@'.$message['host'] : $message['user'];
            }

            foreach ($channels as $channel) {
                if (isset($this->channels[$channel])) {
                    $this->manager->log($this->channels[$channel], $nick, $command, $text);
                }
            }
        }
    }
}
