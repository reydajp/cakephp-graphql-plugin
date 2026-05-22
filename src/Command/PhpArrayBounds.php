<?php
declare(strict_types=1);

namespace CakeGraphQL\Command;

final readonly class PhpArrayBounds
{
    public function __construct(
        public int $open,
        public int $close,
        public int $openOffset,
        public int $closeOffset,
    ) {
    }
}
