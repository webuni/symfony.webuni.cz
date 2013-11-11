<?php

namespace Webuni;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;


class AppKernel extends Kernel
{
    public function __construct($environment, $debug)
    {
        parent::__construct($environment, $debug);
        $autoloaders= spl_autoload_functions();
        AnnotationRegistry::registerLoader(reset($autoloaders));
    }

    public function registerBundles()
    {
        $bundles = array(
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle,
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle,
            new \Webuni\IrcBundle\WebuniIrcBundle,
        );

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->environment.'.yml');
    }

    public function getCacheDir()
    {
        return $this->rootDir.'/../var/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return $this->rootDir.'/../var/logs';
    }

    public function run(Request $request = null, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $request = $request ?: Request::createFromGlobals();
        $response = $this->handle($request, $type, $catch);
        $response->send();
        $this->terminate($request, $response);
    }
}
