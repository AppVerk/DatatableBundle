<?php

namespace AppVerk\DatatableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('datatable', 'array')->children();

        $this->addTemplatesSection($rootNode);

        return $treeBuilder;
    }

    private function addTemplatesSection(NodeBuilder $rootNode)
    {
        $rootNode
            ->arrayNode('templates')
                ->children()
                    ->scalarNode('field_bool')->isRequired()->end()
                    ->scalarNode('field_collection')->isRequired()->end()
                    ->scalarNode('field_object')->isRequired()->end()
                    ->scalarNode('field_timestamps')->isRequired()->end()
                    ->scalarNode('button_submit')->isRequired()->end()
                ->end()
            ->end();
    }
}
