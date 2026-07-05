<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime\Tests;

use InvalidArgumentException;
use LaravelSkir\Runtime\DenseJson;
use LaravelSkir\Runtime\Exceptions\SkirRuntimeException;
use LaravelSkir\Runtime\Type;
use PHPUnit\Framework\TestCase;

final class SkirRuntimeExceptionTest extends TestCase
{
    public function test_it_uses_package_scoped_exceptions_for_invalid_dense_json(): void
    {
        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Invalid dense JSON: Syntax error');

        DenseJson::fromJson(Type::string(), '{');
    }

    public function test_it_uses_package_scoped_exceptions_for_values_that_cannot_be_encoded_as_json(): void
    {
        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Invalid dense JSON: Malformed UTF-8 characters, possibly incorrectly encoded');

        DenseJson::toJson(Type::string(), "\xB1\x31");
    }

    public function test_package_scoped_exceptions_remain_invalid_argument_exceptions(): void
    {
        $exception = new SkirRuntimeException('Invalid Skir value.');

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }
}
