<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime\Tests;

use LaravelSkir\Runtime\MethodDescriptor;
use LaravelSkir\Runtime\Type;
use PHPUnit\Framework\TestCase;

final class MethodDescriptorTest extends TestCase
{
    public function test_it_describes_a_skir_rpc_method(): void
    {
        $requestType = Type::struct([]);
        $responseType = Type::string();

        $method = new MethodDescriptor(
            name: 'GetUser',
            number: 3180856469,
            requestType: $requestType,
            responseType: $responseType,
        );

        $this->assertSame('GetUser', $method->name);
        $this->assertSame(3180856469, $method->number);
        $this->assertSame($requestType, $method->requestType);
        $this->assertSame($responseType, $method->responseType);
    }
}
