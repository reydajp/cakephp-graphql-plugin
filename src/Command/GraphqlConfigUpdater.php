<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

use Cake\Console\Exception\ConsoleException;

final readonly class GraphqlConfigUpdater
{
    private const GRAPHQLITE_QUERIES_PATH = ['Graphql', 'engines', 'Graphqlite', 'queries'];

    public function __construct(private readonly string $projectPath)
    {
    }

    public function addGraphqliteQuery(string $className): void
    {
        $path = $this->configPath();
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ConsoleException(sprintf('Unable to read GraphQL config file: %s', $path));
        }

        $updated = (new PhpArrayPathEditor())->addStringValue(
            $contents,
            self::GRAPHQLITE_QUERIES_PATH,
            $className,
            'Unable to find Graphql.engines.Graphqlite.queries array. Add it manually or use --no-config.',
        );
        $written = file_put_contents($path, $updated);
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
