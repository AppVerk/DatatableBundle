<?php

namespace AppVerk\DatatableBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class DatatableExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('datatable.templates.field_bool', $config['templates']['field_bool']);
        $container->setParameter('datatable.templates.field_collection', $config['templates']['field_collection']);
        $container->setParameter('datatable.templates.field_object', $config['templates']['field_object']);
        $container->setParameter('datatable.templates.field_timestamps', $config['templates']['field_timestamps']);
        $container->setParameter('datatable.templates.button_submit', $config['templates']['button_submit']);
    }
}
