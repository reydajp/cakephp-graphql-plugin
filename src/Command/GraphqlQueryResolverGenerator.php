<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

use Cake\Console\Exception\ConsoleException;
use Cake\Utility\Inflector;

final readonly class GraphqlQueryResolverGenerator
{
    public function __construct(private readonly string $projectPath)
    {
    }

    public function generate(string $name, string $namespace = 'App\\Graphql', bool $single = false): string
    {
        $this->validateName($name);
        $namespace = $this->normalizeNamespace($namespace);
        $model = Inflector::camelize($name);
        $className = $this->shortClassName($name);
        $path = $this->pathFor($namespace, $className);

        if (is_file($path)) {
            throw new ConsoleException(sprintf('GraphQL query resolver already exists: %s', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new ConsoleException(sprintf('Unable to create directory: %s', $directory));
        }

        $written = file_put_contents($path, $this->contents($namespace, $className, $model, $single));
        if ($written === false) {
            throw new ConsoleException(sprintf('Unable to write GraphQL query resolver: %s', $path));
        }

        return $path;
    }

    public function className(string $name, string $namespace = 'App\\Graphql'): string
    {
        $this->validateName($name);

        return $this->normalizeNamespace($namespace) . '\\' . $this->shortClassName($name);
    }

    private function validateName(string $name): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new ConsoleException('GraphQL query name must contain only letters, numbers, and underscores, and start with a letter.');
        }
    }

    private function normalizeNamespace(string $namespace): string
    {
        $namespace = trim($namespace, '\\');
        if (!preg_match('/^App(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
            throw new ConsoleException('GraphQL query namespace must be App or a valid namespace below App.');
        }

        return $namespace;
    }

    private function shortClassName(string $name): string
    {
        return Inflector::camelize($name) . 'Query';
    }

    private function pathFor(string $namespace, string $className): string
    {
        $relativeNamespace = trim($namespace, '\\');
        if ($relativeNamespace === 'App') {
            $relativeNamespace = '';
        } elseif (str_starts_with($relativeNamespace, 'App\\')) {
            $relativeNamespace = substr($relativeNamespace, 4);
        }

        $segments = $relativeNamespace === '' ? [] : explode('\\', $relativeNamespace);

        return rtrim($this->projectPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'src'
            . ($segments === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments))
            . DIRECTORY_SEPARATOR
            . $className
            . '.php';
    }

    private function contents(string $namespace, string $className, string $model, bool $single): string
    {
        $method = $single
            ? Inflector::variable(Inflector::singularize($model))
            : Inflector::variable($model);
        $body = $single
            ? $this->singleMethod($method, $model)
            : $this->collectionMethod($method, $model);
        $imports = [
            'use Cake\\ORM\\Locator\\LocatorAwareTrait;',
            'use TheCodingMachine\\GraphQLite\\Annotations\\Query;',
        ];

        if ($single) {
            $imports[] = 'use TheCodingMachine\\GraphQLite\\Annotations\\UseInputType;';
        }

        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . 'namespace ' . trim($namespace, '\\') . ";\n\n"
            . implode("\n", $imports)
            . "\n\n"
            . 'final class ' . $className . "\n"
            . "{\n"
            . "    use LocatorAwareTrait;\n\n"
            . $body
            . "}\n";
    }

    private function collectionMethod(string $method, string $model): string
    {
        return "    #[Query]\n"
            . '    public function ' . $method . "(int \$limit = 50): array\n"
            . "    {\n"
            . "        \$limit = max(1, min(\$limit, 100));\n\n"
            . "        return \$this->fetchTable('" . $model . "')->find()->limit(\$limit)->all()->toList();\n"
            . "    }\n";
    }

    private function singleMethod(string $method, string $model): string
    {
        return "    #[Query]\n"
            . '    public function ' . $method . "(#[UseInputType('ID!')] string \$id): mixed\n"
            . "    {\n"
            . "        return \$this->fetchTable('" . $model . "')->get(\$id);\n"
            . "    }\n";
    }
}
