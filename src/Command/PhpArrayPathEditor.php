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
        $tokens = PhpTokenStream::fromContents($contents);
        $array = $this->arrayAtPath($tokens, $path);

        if ($array === null) {
            throw new ConsoleException($missingPathMessage);
        }

        if ($this->arrayContainsValue($tokens, $array, $value)) {
            return $contents;
        }

        return $this->insertStringValue($contents, $array, $value);
    }

    /**
     * @param list<string> $path
     */
    private function arrayAtPath(PhpTokenStream $tokens, array $path): ?PhpArrayBounds
    {
        $array = $tokens->returnedArray();
        foreach ($path as $key) {
            if ($array === null) {
                return null;
            }
            $array = $this->arrayValueForKey($tokens, $array, $key);
        }

        return $array;
    }

    private function arrayValueForKey(PhpTokenStream $tokens, PhpArrayBounds $array, string $key): ?PhpArrayBounds
    {
        for ($index = $array->open + 1; $index < $array->close; $index++) {
            if ($tokens->isNestedArrayOpen($index)) {
                $index = $tokens->matchingClose($index);
                continue;
            }

            $arrow = $tokens->nextMeaningful($index + 1);
            if ($tokens->stringValue($index) !== $key || $arrow === null || $tokens->id($arrow) !== T_DOUBLE_ARROW) {
                continue;
            }

            return $tokens->arrayAfter($arrow);
        }

        return null;
    }

    private function arrayContainsValue(PhpTokenStream $tokens, PhpArrayBounds $array, string $value): bool
    {
        for ($index = $array->open + 1; $index < $array->close; $index++) {
            if ($tokens->isNestedArrayOpen($index)) {
                $index = $tokens->matchingClose($index);
                continue;
            }

            if ($tokens->stringValue($index) === $value || $tokens->isClassConstant($index, $value)) {
                return true;
            }
        }

        return false;
    }

    private function insertStringValue(string $contents, PhpArrayBounds $array, string $value): string
    {
        $inside = substr($contents, $array->openOffset + 1, $array->closeOffset - $array->openOffset - 1);
        $closingIndent = $this->closingIndent($contents, $array, $inside);
        $line = $closingIndent . '    ' . $this->quotePhpString($value) . ",\n";

        if (trim($inside) === '') {
            return substr($contents, 0, $array->openOffset + 1)
                . "\n"
                . $line
                . $closingIndent
                . substr($contents, $array->closeOffset);
        }

        $closingLineStart = (int)strrpos(substr($contents, 0, $array->closeOffset), "\n") + 1;

        return substr($contents, 0, $closingLineStart) . $line . substr($contents, $closingLineStart);
    }

    private function closingIndent(string $contents, PhpArrayBounds $array, string $inside): string
    {
        if (str_contains($inside, "\n")) {
            $lineStart = (int)strrpos(substr($contents, 0, $array->closeOffset), "\n") + 1;

            return substr($contents, $lineStart, $array->closeOffset - $lineStart);
        }

        $lineStart = (int)strrpos(substr($contents, 0, $array->openOffset), "\n") + 1;
        preg_match('/^[ \t]*/', substr($contents, $lineStart, $array->openOffset - $lineStart), $matches);

        return $matches[0] ?? '';
    }

    private function quotePhpString(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
