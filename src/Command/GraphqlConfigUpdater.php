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
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ConsoleException(sprintf('Unable to read GraphQL config file: %s', $path));
        }

        $updated = $this->addQueryToConfigContents($contents, $className);
        $written = file_put_contents($path, $updated);
        if ($written === false) {
            throw new ConsoleException(sprintf('Unable to update GraphQL config file: %s', $path));
        }
    }

    private function addQueryToConfigContents(string $contents, string $className): string
    {
        $tokens = $this->tokens($contents);
        $pairs = $this->matchingPairs($tokens);
        $queries = $this->findQueriesArray($tokens, $pairs);

        if ($queries === null) {
            throw new ConsoleException(
                'Unable to find Graphql.engines.Graphqlite.queries array. Add it manually or use --no-config.',
            );
        }

        if ($this->arrayContainsClassName($tokens, $pairs, $queries['open'], $queries['close'], $className)) {
            return $contents;
        }

        return $this->insertArrayValue($contents, $queries['openOffset'], $queries['closeOffset'], $className);
    }

    /**
     * @return list<array{id: int|null, text: string, offset: int}>
     */
    private function tokens(string $contents): array
    {
        $tokens = [];
        $offset = 0;
        foreach (token_get_all($contents) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $tokens[] = [
                'id' => is_array($token) ? $token[0] : null,
                'text' => $text,
                'offset' => $offset,
            ];
            $offset += strlen($text);
        }

        return $tokens;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @return array<int, int>
     */
    private function matchingPairs(array $tokens): array
    {
        $pairs = [];
        $stack = [];
        foreach ($tokens as $index => $token) {
            if ($token['text'] === '[' || $token['text'] === '(') {
                $stack[] = $index;
                continue;
            }

            if (($token['text'] === ']' || $token['text'] === ')') && $stack !== []) {
                $open = array_pop($stack);
                $pairs[$open] = $index;
                $pairs[$index] = $open;
            }
        }

        return $pairs;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function findQueriesArray(array $tokens, array $pairs): ?array
    {
        $root = $this->findReturnedArray($tokens, $pairs);
        if ($root === null) {
            return null;
        }

        $graphql = $this->findArrayValueForKey($tokens, $pairs, $root['open'], $root['close'], 'Graphql');
        if ($graphql === null) {
            return null;
        }

        $engines = $this->findArrayValueForKey($tokens, $pairs, $graphql['open'], $graphql['close'], 'engines');
        if ($engines === null) {
            return null;
        }

        $graphqlite = $this->findArrayValueForKey($tokens, $pairs, $engines['open'], $engines['close'], 'Graphqlite');
        if ($graphqlite === null) {
            return null;
        }

        return $this->findArrayValueForKey($tokens, $pairs, $graphqlite['open'], $graphqlite['close'], 'queries');
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function findReturnedArray(array $tokens, array $pairs): ?array
    {
        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_RETURN) {
                continue;
            }

            return $this->arrayBoundsAfter($tokens, $pairs, $index);
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function findArrayValueForKey(array $tokens, array $pairs, int $open, int $close, string $key): ?array
    {
        for ($index = $open + 1; $index < $close; $index++) {
            if ($this->isNestedArrayOpen($tokens, $pairs, $index)) {
                $index = $pairs[$index];
                continue;
            }

            if ($this->stringValue($tokens[$index]) !== $key) {
                continue;
            }

            $arrow = $this->nextMeaningful($tokens, $index + 1);
            if ($arrow === null || $tokens[$arrow]['id'] !== T_DOUBLE_ARROW) {
                continue;
            }

            return $this->arrayBoundsAfter($tokens, $pairs, $arrow);
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function arrayBoundsAfter(array $tokens, array $pairs, int $index): ?array
    {
        $value = $this->nextMeaningful($tokens, $index + 1);
        if ($value === null) {
            return null;
        }

        if ($tokens[$value]['text'] === '[' && isset($pairs[$value])) {
            return [
                'open' => $value,
                'close' => $pairs[$value],
                'openOffset' => $tokens[$value]['offset'],
                'closeOffset' => $tokens[$pairs[$value]]['offset'],
            ];
        }

        if ($tokens[$value]['id'] === T_ARRAY) {
            $open = $this->nextMeaningful($tokens, $value + 1);
            if ($open !== null && $tokens[$open]['text'] === '(' && isset($pairs[$open])) {
                return [
                    'open' => $open,
                    'close' => $pairs[$open],
                    'openOffset' => $tokens[$open]['offset'],
                    'closeOffset' => $tokens[$pairs[$open]]['offset'],
                ];
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function nextMeaningful(array $tokens, int $index): ?int
    {
        $count = count($tokens);
        while ($index < $count) {
            if (!$this->isTrivia($tokens[$index])) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    /**
     * @param array{id: int|null, text: string, offset: int} $token
     */
    private function isTrivia(array $token): bool
    {
        return in_array($token['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     */
    private function isNestedArrayOpen(array $tokens, array $pairs, int $index): bool
    {
        return isset($pairs[$index]) && (
            $tokens[$index]['text'] === '['
            || ($tokens[$index]['text'] === '(' && $tokens[$this->previousMeaningful($tokens, $index - 1) ?? $index]['id'] === T_ARRAY)
        );
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function previousMeaningful(array $tokens, int $index): ?int
    {
        while ($index >= 0) {
            if (!$this->isTrivia($tokens[$index])) {
                return $index;
            }
            $index--;
        }

        return null;
    }

    /**
     * @param array{id: int|null, text: string, offset: int} $token
     */
    private function stringValue(array $token): ?string
    {
        if ($token['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $quote = $token['text'][0];
        $value = substr($token['text'], 1, -1);
        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $value);
        }

        return stripcslashes($value);
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     */
    private function arrayContainsClassName(
        array $tokens,
        array $pairs,
        int $open,
        int $close,
        string $className,
    ): bool {
        for ($index = $open + 1; $index < $close; $index++) {
            if ($this->isNestedArrayOpen($tokens, $pairs, $index)) {
                $index = $pairs[$index];
                continue;
            }

            if ($this->stringValue($tokens[$index]) === $className) {
                return true;
            }

            if ($this->isClassConstantReference($tokens, $index, $className)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function isClassConstantReference(array $tokens, int $index, string $className): bool
    {
        if (!in_array($tokens[$index]['id'], $this->classNameTokenIds(), true)) {
            return false;
        }

        if (ltrim($tokens[$index]['text'], '\\') !== ltrim($className, '\\')) {
            return false;
        }

        $doubleColon = $this->nextMeaningful($tokens, $index + 1);
        $class = $doubleColon === null ? null : $this->nextMeaningful($tokens, $doubleColon + 1);

        return $doubleColon !== null
            && $class !== null
            && $tokens[$doubleColon]['id'] === T_DOUBLE_COLON
            && $tokens[$class]['id'] === T_CLASS;
    }

    /**
     * @return list<int>
     */
    private function classNameTokenIds(): array
    {
        $ids = [T_STRING];
        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $constant) {
            if (defined($constant)) {
                $ids[] = constant($constant);
            }
        }

        return $ids;
    }

    private function insertArrayValue(string $contents, int $openOffset, int $closeOffset, string $className): string
    {
        $inside = substr($contents, $openOffset + 1, $closeOffset - $openOffset - 1);
        $closingLineStart = (int)strrpos(substr($contents, 0, $closeOffset), "\n") + 1;
        $closingIndent = substr($contents, $closingLineStart, $closeOffset - $closingLineStart);
        if (!str_contains($inside, "\n")) {
            $openingLineStart = (int)strrpos(substr($contents, 0, $openOffset), "\n") + 1;
            preg_match('/^[ \t]*/', substr($contents, $openingLineStart, $openOffset - $openingLineStart), $matches);
            $closingIndent = $matches[0] ?? '';
        }
        $entryIndent = $closingIndent . '    ';
        $quotedClassName = $this->quotePhpString($className);

        if (trim($inside) === '') {
            return substr($contents, 0, $openOffset + 1)
                . "\n"
                . $entryIndent
                . $quotedClassName
                . ",\n"
                . $closingIndent
                . substr($contents, $closeOffset);
        }

        return substr($contents, 0, $closingLineStart)
            . $entryIndent
            . $quotedClassName
            . ",\n"
            . substr($contents, $closingLineStart);
    }

    private function quotePhpString(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
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
