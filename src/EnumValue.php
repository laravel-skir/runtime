<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime;

final readonly class EnumValue
{
    private function __construct(
        public string $name,
        public mixed $value,
        public bool $wrapper,
    ) {}

    public static function constant(string $name): self
    {
        return new self($name, null, false);
    }

    public static function wrapper(string $name, mixed $value): self
    {
        return new self($name, $value, true);
    }
}
