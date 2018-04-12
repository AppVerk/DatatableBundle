<?php

namespace AppVerk\DatatableBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigProvider
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Sets the container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     * @required
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getTemplate(string $template) : string
    {
        return $this->container->getParameter("datatable.templates.$template");
    }
}
