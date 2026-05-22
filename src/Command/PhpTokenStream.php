<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

final class PhpTokenStream
{
    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @param array<int, int> $pairs
     */
    private function __construct(
        private readonly array $tokens,
        private readonly array $pairs,
    ) {
    }

    public static function fromContents(string $contents): self
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

        return new self($tokens, self::matchingPairs($tokens));
    }

    public function id(int $index): ?int
    {
        return $this->tokens[$index]['id'];
    }

    public function text(int $index): string
    {
        return $this->tokens[$index]['text'];
    }

    public function offset(int $index): int
    {
        return $this->tokens[$index]['offset'];
    }

    public function matchingClose(int $open): int
    {
        return $this->pairs[$open];
    }

    public function returnedArray(): ?PhpArrayBounds
    {
        foreach ($this->tokens as $index => $token) {
            if ($token['id'] === T_RETURN) {
                return $this->arrayAfter($index);
            }
        }

        return null;
    }

    public function arrayAfter(int $index): ?PhpArrayBounds
    {
        $value = $this->nextMeaningful($index + 1);
        if ($value === null) {
            return null;
        }

        if ($this->text($value) === '[' && isset($this->pairs[$value])) {
            return $this->arrayBounds($value);
        }

        if ($this->id($value) !== T_ARRAY) {
            return null;
        }

        $open = $this->nextMeaningful($value + 1);
        if ($open === null || $this->text($open) !== '(' || !isset($this->pairs[$open])) {
            return null;
        }

        return $this->arrayBounds($open);
    }

    public function nextMeaningful(int $index): ?int
    {
        for ($count = count($this->tokens); $index < $count; $index++) {
            if (!$this->isTrivia($index)) {
                return $index;
            }
        }

        return null;
    }

    public function isNestedArrayOpen(int $index): bool
    {
        if (!isset($this->pairs[$index])) {
            return false;
        }

        if ($this->text($index) === '[') {
            return true;
        }

        $previous = $this->previousMeaningful($index - 1);

        return $this->text($index) === '('
            && $previous !== null
            && $this->id($previous) === T_ARRAY;
    }

    public function stringValue(int $index): ?string
    {
        if ($this->id($index) !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $text = $this->text($index);
        $value = substr($text, 1, -1);
        if ($text[0] === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $value);
        }

        return stripcslashes($value);
    }

    public function isClassConstant(int $index, string $className): bool
    {
        if (!in_array($this->id($index), $this->classNameTokenIds(), true)) {
            return false;
        }

        $doubleColon = $this->nextMeaningful($index + 1);
        $class = $doubleColon === null ? null : $this->nextMeaningful($doubleColon + 1);

        return ltrim($this->text($index), '\\') === ltrim($className, '\\')
            && $doubleColon !== null
            && $class !== null
            && $this->id($doubleColon) === T_DOUBLE_COLON
            && $this->id($class) === T_CLASS;
    }

    /**
     * @param list<array{id: int|null, text: string, offset: int}> $tokens
     * @return array<int, int>
     */
    private static function matchingPairs(array $tokens): array
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

    private function arrayBounds(int $open): PhpArrayBounds
    {
        $close = $this->pairs[$open];

        return new PhpArrayBounds(
            open: $open,
            close: $close,
            openOffset: $this->offset($open),
            closeOffset: $this->offset($close),
        );
    }

    private function previousMeaningful(int $index): ?int
    {
        for (; $index >= 0; $index--) {
            if (!$this->isTrivia($index)) {
                return $index;
            }
        }

        return null;
    }

    private function isTrivia(int $index): bool
    {
        return in_array($this->id($index), [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
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
}
