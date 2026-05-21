<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Exception\ConsoleException;

final class BakeGraphqlQueryCommand extends Command
{
    private GraphqlQueryResolverGenerator $generator;
    private GraphqlConfigUpdater $configUpdater;

    public function __construct(
        ?CommandFactoryInterface $factory = null,
        ?GraphqlQueryResolverGenerator $generator = null,
        ?GraphqlConfigUpdater $configUpdater = null,
    ) {
        if (method_exists(parent::class, '__construct')) {
            parent::__construct($factory);
        }

        $projectPath = (string)getcwd();
        $this->generator = $generator ?? new GraphqlQueryResolverGenerator($projectPath);
        $this->configUpdater = $configUpdater ?? new GraphqlConfigUpdater($projectPath);
    }

    public static function getDescription(): string
    {
        return 'Generate a GraphQLite query resolver.';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addArgument('name', [
                'help' => 'The model/table name to generate a query resolver for.',
                'required' => true,
            ])
            ->addOption('single', [
                'help' => 'Generate a single-record query instead of a collection query.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('namespace', [
                'help' => 'The namespace for the generated resolver.',
                'default' => 'App\\Graphql',
            ])
            ->addOption('no-config', [
                'help' => 'Skip GraphQL config updates.',
                'boolean' => true,
                'default' => false,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $name = (string)$args->getArgument('name');
        $namespace = (string)$args->getOption('namespace');
        try {
            $path = $this->generator->generate(
                $name,
                $namespace,
                (bool)$args->getOption('single'),
            );

            if (!$args->getOption('no-config')) {
                $this->configUpdater->addGraphqliteQuery($this->generator->className($name, $namespace));
            }
        } catch (ConsoleException $e) {
            if (isset($path) && file_exists($path)) {
                unlink($path);
            }
            $io->err($e->getMessage());

            return static::CODE_ERROR;
        }

        $io->out(sprintf('Created %s', $path));

        return static::CODE_SUCCESS;
    }
}
