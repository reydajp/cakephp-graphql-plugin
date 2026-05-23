<?php
declare(strict_types=1);

namespace CakeGraphQL\Test\TestCase\Command;

use Cake\Console\CommandCollection;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use CakeGraphQL\CakeGraphQLPlugin;
use CakeGraphQL\Command\BakeGraphqlQueryCommand;
use CakeGraphQL\Command\GraphqlConfigUpdater;
use CakeGraphQL\Command\GraphqlQueryResolverGenerator;
use PHPUnit\Framework\TestCase;

final class BakeGraphqlQueryCommandTest extends TestCase
{
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectPath = sys_get_temp_dir() . '/cakegraphql_bake_' . bin2hex(random_bytes(6));
        mkdir($this->projectPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectPath);

        parent::tearDown();
    }

    public function testPluginRegistersBakeGraphqlQueryCommand(): void
    {
        $commands = (new CakeGraphQLPlugin())->console(new CommandCollection());

        $this->assertTrue($commands->has('bake graphql query'));
        $this->assertSame(BakeGraphqlQueryCommand::class, $commands->get('bake graphql query'));
    }

    public function testCommandGeneratesCollectionQueryInDefaultNamespace(): void
    {
        $this->writeConfig([
            'Graphql' => [
                'engines' => [
                    'Graphqlite' => [
                        'queries' => [],
                    ],
                ],
            ],
        ]);

        $exitCode = $this->runCommand(['Users']);

        $this->assertSame(0, $exitCode);
        $path = $this->projectPath . '/src/Graphql/UsersQuery.php';
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('namespace App\\Graphql;', $contents);
        $this->assertStringContainsString('final class UsersQuery', $contents);
        $this->assertStringContainsString('use LocatorAwareTrait;', $contents);
        $this->assertStringContainsString('#[Query]', $contents);
        $this->assertStringContainsString('public function users(int $limit = 50): array', $contents);
        $this->assertStringContainsString('$limit = max(1, min($limit, 100));', $contents);
        $this->assertStringContainsString("return \$this->fetchTable('Users')->find()->limit(\$limit)->all()->toList();", $contents);
        $this->assertSame(['App\\Graphql\\UsersQuery'], $this->readGraphqliteQueries());
    }

    public function testCommandGeneratesSingleRecordQuery(): void
    {
        $this->writeConfig([
            'Graphql' => [
                'engines' => [
                    'Graphqlite' => [
                        'queries' => [],
                    ],
                ],
            ],
        ]);

        $exitCode = $this->runCommand(['Users', '--single']);

        $this->assertSame(0, $exitCode);
        $contents = file_get_contents($this->projectPath . '/src/Graphql/UsersQuery.php');
        $this->assertStringContainsString("use TheCodingMachine\\GraphQLite\\Annotations\\UseInputType;", $contents);
        $this->assertStringContainsString('public function user(#[UseInputType(\'ID!\')] string $id): mixed', $contents);
        $this->assertStringContainsString("return \$this->fetchTable('Users')->get(\$id);", $contents);
    }

    public function testCommandGeneratesIntoCustomNamespace(): void
    {
        $this->writeConfig([
            'Graphql' => [
                'engines' => [
                    'Graphqlite' => [
                        'queries' => [],
                    ],
                ],
            ],
        ]);

        $exitCode = $this->runCommand(['Users', '--namespace', 'App\\Api\\Graphql']);

        $this->assertSame(0, $exitCode);
        $path = $this->projectPath . '/src/Api/Graphql/UsersQuery.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace App\\Api\\Graphql;', file_get_contents($path));
        $this->assertSame(['App\\Api\\Graphql\\UsersQuery'], $this->readGraphqliteQueries());
    }

    public function testCommandDoesNotOverwriteExistingFile(): void
    {
        $this->writeConfig([
            'Graphql' => [
                'engines' => [
                    'Graphqlite' => [
                        'queries' => [],
                    ],
                ],
            ],
        ]);
        $path = $this->projectPath . '/src/Graphql/UsersQuery.php';
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'existing');

        $exitCode = $this->runCommand(['Users']);

        $this->assertSame(1, $exitCode);
        $this->assertSame('existing', file_get_contents($path));
    }

    public function testCommandSkipsConfigUpdateWithNoConfigOption(): void
    {
        $exitCode = $this->runCommand(['Users', '--no-config']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->projectPath . '/src/Graphql/UsersQuery.php');
        $this->assertFileDoesNotExist($this->projectPath . '/config/app_local.php');
    }

    public function testCommandRejectsInvalidModelName(): void
    {
        $exitCode = $this->runCommand(['../Users', '--no-config']);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($this->projectPath . '/src/UsersQuery.php');
    }

    public function testCommandRejectsNamespaceOutsideAppNamespace(): void
    {
        $exitCode = $this->runCommand(['Users', '--namespace', '..\\Outside', '--no-config']);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($this->projectPath . '/Outside/UsersQuery.php');
        $this->assertFileDoesNotExist($this->projectPath . '/src/../Outside/UsersQuery.php');
    }

    public function testCommandDoesNotDuplicateConfigEntry(): void
    {
        $this->writeConfig([
            'Graphql' => [
                'engines' => [
                    'Graphqlite' => [
                        'queries' => ['App\\Graphql\\UsersQuery'],
                    ],
                ],
            ],
        ]);

        $exitCode = $this->runCommand(['Users']);

        $this->assertSame(0, $exitCode);
        $this->assertSame(['App\\Graphql\\UsersQuery'], $this->readGraphqliteQueries());
    }

    public function testConfigUpdatePreservesDynamicConfigExpressions(): void
    {
        $this->writeConfigContents(<<<'PHP'
<?php
return [
    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [
                ],
            ],
        ],
    ],
];
PHP);

        (new GraphqlConfigUpdater($this->projectPath))->addGraphqliteQuery('App\\Graphql\\UsersQuery');

        $contents = file_get_contents($this->projectPath . '/config/app_local.php');
        $this->assertStringContainsString("'salt' => env('SECURITY_SALT')", $contents);
        $this->assertStringContainsString("'App\\\\Graphql\\\\UsersQuery',", $contents);
    }

    public function testConfigUpdateDoesNotDuplicateClassConstantEntry(): void
    {
        $this->writeConfigContents(<<<'PHP'
<?php
return [
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [
                    App\Graphql\UsersQuery::class,
                ],
            ],
        ],
    ],
];
PHP);

        (new GraphqlConfigUpdater($this->projectPath))->addGraphqliteQuery('App\\Graphql\\UsersQuery');

        $contents = file_get_contents($this->projectPath . '/config/app_local.php');
        $this->assertSame(1, substr_count($contents, 'UsersQuery'));
    }

    public function testConfigUpdateSupportsInlineEmptyQueriesArray(): void
    {
        $this->writeConfigContents(<<<'PHP'
<?php
return [
    'Graphql' => [
        'engines' => [
            'Graphqlite' => [
                'queries' => [],
            ],
        ],
    ],
];
PHP);

        (new GraphqlConfigUpdater($this->projectPath))->addGraphqliteQuery('App\\Graphql\\UsersQuery');

        $config = require $this->projectPath . '/config/app_local.php';
        $this->assertSame(['App\\Graphql\\UsersQuery'], $config['Graphql']['engines']['Graphqlite']['queries']);
    }

    public function testCommandRollsBackGeneratedFileWhenConfigUpdateFails(): void
    {
        $exitCode = $this->runCommand(['Users']);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($this->projectPath . '/src/Graphql/UsersQuery.php');
    }

    /**
     * @param list<string> $argv
     */
    private function runCommand(array $argv): ?int
    {
        $command = new BakeGraphqlQueryCommand(
            null,
            new GraphqlQueryResolverGenerator($this->projectPath),
            new GraphqlConfigUpdater($this->projectPath),
        );
        $command->setName('cake bake graphql query');

        return $command->run($argv, new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput()));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeConfig(array $config): void
    {
        $directory = $this->projectPath . '/config';
        mkdir($directory, 0777, true);
        file_put_contents(
            $directory . '/app_local.php',
            "<?php\nreturn " . var_export($config, true) . ";\n",
        );
    }

    private function writeConfigContents(string $contents): void
    {
        $directory = $this->projectPath . '/config';
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/app_local.php', $contents);
    }

    /**
     * @return list<class-string>
     */
    private function readGraphqliteQueries(): array
    {
        $config = require $this->projectPath . '/config/app_local.php';

        return $config['Graphql']['engines']['Graphqlite']['queries'];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        assert(is_array($items));
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }
            unlink($child);
        }
        rmdir($path);
    }
}
