<?php

declare(strict_types=1);

namespace Skir\Runtime\Exceptions;

use InvalidArgumentException;
use Throwable;

final class SkirRuntimeException extends InvalidArgumentException
{
    public static function invalidDenseJson(string $message, ?Throwable $previous = null): self
    {
        return new self("Invalid dense JSON: {$message}", previous: $previous);
    }

    public static function invalidCbor(string $message, ?Throwable $previous = null): self
    {
        return new self("Invalid CBOR: {$message}", previous: $previous);
    }

    public static function invalidValue(string $message): self
    {
        return new self($message);
    }

    public static function missingCborDependency(): self
    {
        return new self('Skir CBOR support requires the [spomky-labs/cbor-php] Composer package.');
    }
}
