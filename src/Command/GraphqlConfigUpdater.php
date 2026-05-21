<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

use Cake\Console\Exception\ConsoleException;

final class GraphqlConfigUpdater
{
    public function __construct(private readonly string $projectPath)
    {
    }

    public function addGraphqliteQuery(string $className): void
    {
        $path = $this->configPath();
        $config = require $path;
        if (!is_array($config)) {
            throw new ConsoleException(sprintf('GraphQL config file must return an array: %s', $path));
        }

        $config['Graphql'] ??= [];
        $config['Graphql']['engines'] ??= [];
        $config['Graphql']['engines']['Graphqlite'] ??= [];
        $config['Graphql']['engines']['Graphqlite']['queries'] ??= [];

        if (!is_array($config['Graphql']['engines']['Graphqlite']['queries'])) {
            throw new ConsoleException('Graphql.engines.Graphqlite.queries must be an array.');
        }

        if (!in_array($className, $config['Graphql']['engines']['Graphqlite']['queries'], true)) {
            $config['Graphql']['engines']['Graphqlite']['queries'][] = $className;
        }

        $written = file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n");
        if ($written === false) {
            throw new ConsoleException(sprintf('Unable to update GraphQL config file: %s', $path));
        }
    }

    private function configPath(): string
    {
        $configDirectory = rtrim($this->projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
        $candidates = [
            $configDirectory . DIRECTORY_SEPARATOR . 'app_local.php',
            $configDirectory . DIRECTORY_SEPARATOR . 'app.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new ConsoleException('Unable to find config/app_local.php or config/app.php for GraphQL config update.');
    }
}
