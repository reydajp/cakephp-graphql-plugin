<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

use Cake\Console\Exception\ConsoleException;

final class PhpArrayPathEditor
{
    /**
     * @param list<string> $path
     */
    public function addStringValue(string $contents, array $path, string $value, string $missingPathMessage): string
    {
        $tokens = $this->tokens($contents);
        $pairs = $this->matchingPairs($tokens);
        $array = $this->arrayAtPath($tokens, $pairs, $path);

        if ($array === null) {
            throw new ConsoleException($missingPathMessage);
        }

        if ($this->arrayContainsValue($tokens, $pairs, $array['open'], $array['close'], $value)) {
            return $contents;
        }

        return $this->insertStringValue($contents, $array['openOffset'], $array['closeOffset'], $value);
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
     * @param list<string> $path
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function arrayAtPath(array $tokens, array $pairs, array $path): ?array
    {
        $array = $this->returnedArray($tokens, $pairs);
        foreach ($path as $key) {
            if ($array === null) {
                return null;
            }
            $array = $this->arrayValueForKey($tokens, $pairs, $array['open'], $array['close'], $key);
        }

        return $array;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function returnedArray(array $tokens, array $pairs): ?array
    {
        foreach ($tokens as $index => $token) {
            if ($token['id'] === T_RETURN) {
                return $this->arrayAfter($tokens, $pairs, $index);
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function arrayValueForKey(array $tokens, array $pairs, int $open, int $close, string $key): ?array
    {
        for ($index = $open + 1; $index < $close; $index++) {
            if ($this->isNestedArrayOpen($tokens, $pairs, $index)) {
                $index = $pairs[$index];
                continue;
            }

            $arrow = $this->nextMeaningful($tokens, $index + 1);
            if ($this->stringValue($tokens[$index]) !== $key || $arrow === null || $tokens[$arrow]['id'] !== T_DOUBLE_ARROW) {
                continue;
            }

            return $this->arrayAfter($tokens, $pairs, $arrow);
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}|null
     */
    private function arrayAfter(array $tokens, array $pairs, int $index): ?array
    {
        $value = $this->nextMeaningful($tokens, $index + 1);
        if ($value === null) {
            return null;
        }

        if ($tokens[$value]['text'] === '[' && isset($pairs[$value])) {
            return $this->arrayBounds($tokens, $pairs, $value);
        }

        if ($tokens[$value]['id'] !== T_ARRAY) {
            return null;
        }

        $open = $this->nextMeaningful($tokens, $value + 1);
        if ($open === null || $tokens[$open]['text'] !== '(' || !isset($pairs[$open])) {
            return null;
        }

        return $this->arrayBounds($tokens, $pairs, $open);
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     * @return array{open: int, close: int, openOffset: int, closeOffset: int}
     */
    private function arrayBounds(array $tokens, array $pairs, int $open): array
    {
        $close = $pairs[$open];

        return [
            'open' => $open,
            'close' => $close,
            'openOffset' => $tokens[$open]['offset'],
            'closeOffset' => $tokens[$close]['offset'],
        ];
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function nextMeaningful(array $tokens, int $index): ?int
    {
        for ($count = count($tokens); $index < $count; $index++) {
            if (!$this->isTrivia($tokens[$index])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function previousMeaningful(array $tokens, int $index): ?int
    {
        for (; $index >= 0; $index--) {
            if (!$this->isTrivia($tokens[$index])) {
                return $index;
            }
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
        if (!isset($pairs[$index])) {
            return false;
        }

        if ($tokens[$index]['text'] === '[') {
            return true;
        }

        $previous = $this->previousMeaningful($tokens, $index - 1);

        return $tokens[$index]['text'] === '('
            && $previous !== null
            && $tokens[$previous]['id'] === T_ARRAY;
    }

    /**
     * @param array{id: int|null, text: string, offset: int} $token
     */
    private function stringValue(array $token): ?string
    {
        if ($token['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $value = substr($token['text'], 1, -1);
        if ($token['text'][0] === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $value);
        }

        return stripcslashes($value);
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     */
    private function arrayContainsValue(array $tokens, array $pairs, int $open, int $close, string $value): bool
    {
        for ($index = $open + 1; $index < $close; $index++) {
            if ($this->isNestedArrayOpen($tokens, $pairs, $index)) {
                $index = $pairs[$index];
                continue;
            }

            if ($this->stringValue($tokens[$index]) === $value || $this->isClassConstant($tokens, $index, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     */
    private function isClassConstant(array $tokens, int $index, string $className): bool
    {
        if (!in_array($tokens[$index]['id'], $this->classNameTokenIds(), true)) {
            return false;
        }

        $doubleColon = $this->nextMeaningful($tokens, $index + 1);
        $class = $doubleColon === null ? null : $this->nextMeaningful($tokens, $doubleColon + 1);

        return ltrim($tokens[$index]['text'], '\\') === ltrim($className, '\\')
            && $doubleColon !== null
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

    private function insertStringValue(string $contents, int $openOffset, int $closeOffset, string $value): string
    {
        $inside = substr($contents, $openOffset + 1, $closeOffset - $openOffset - 1);
        $closingIndent = $this->closingIndent($contents, $openOffset, $closeOffset, $inside);
        $line = $closingIndent . '    ' . $this->quotePhpString($value) . ",\n";

        if (trim($inside) === '') {
            return substr($contents, 0, $openOffset + 1)
                . "\n"
                . $line
                . $closingIndent
                . substr($contents, $closeOffset);
        }

        $closingLineStart = (int)strrpos(substr($contents, 0, $closeOffset), "\n") + 1;

        return substr($contents, 0, $closingLineStart) . $line . substr($contents, $closingLineStart);
    }

    private function closingIndent(string $contents, int $openOffset, int $closeOffset, string $inside): string
    {
        if (str_contains($inside, "\n")) {
            $lineStart = (int)strrpos(substr($contents, 0, $closeOffset), "\n") + 1;

            return substr($contents, $lineStart, $closeOffset - $lineStart);
        }

        $lineStart = (int)strrpos(substr($contents, 0, $openOffset), "\n") + 1;
        preg_match('/^[ \t]*/', substr($contents, $lineStart, $openOffset - $lineStart), $matches);

        return $matches[0] ?? '';
    }

    private function quotePhpString(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
