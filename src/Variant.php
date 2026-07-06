<?php

declare(strict_types=1);

namespace Skir\Runtime;

final readonly class Variant
{
    private function __construct(
        public string $name,
        public int $number,
        public ?Type $payloadType,
    ) {}

    public static function constant(string $name, int $number): self
    {
        return new self($name, $number, null);
    }

    public static function wrapper(string $name, int $number, Type $payloadType): self
    {
        return new self($name, $number, $payloadType);
    }

    public function isWrapper(): bool
    {
        return $this->payloadType !== null;
    }
}
