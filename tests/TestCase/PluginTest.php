<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase;

use Cake\Core\BasePlugin;
use Cake\Core\PluginCollection;
use CakeGraphQL\CakeGraphQLPlugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testPluginExtendsCakeBasePlugin(): void
    {
        $this->assertTrue(class_exists(CakeGraphQLPlugin::class));
        $this->assertTrue(is_subclass_of(CakeGraphQLPlugin::class, BasePlugin::class));
    }

    public function testCakePluginCollectionUsesNamedPluginClass(): void
    {
        $plugin = (new PluginCollection())->create('CakeGraphQL');

        $this->assertInstanceOf(CakeGraphQLPlugin::class, $plugin);
    }
}
