<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase;

use Cake\Core\BasePlugin;
use CakeGraphQL\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testPluginExtendsCakeBasePlugin(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
        $this->assertTrue(is_subclass_of(Plugin::class, BasePlugin::class));
    }
}
