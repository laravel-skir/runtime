<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime\Tests;

use LaravelSkir\Runtime\Cbor;
use LaravelSkir\Runtime\Exceptions\SkirRuntimeException;
use LaravelSkir\Runtime\Field;
use LaravelSkir\Runtime\MethodDescriptor;
use LaravelSkir\Runtime\Type;
use PHPUnit\Framework\TestCase;

final class CborTest extends TestCase
{
    public function test_it_round_trips_dense_skir_values_as_cbor(): void
    {
        $userType = Type::struct([
            Field::value('id', 0, Type::int32()),
            Field::value('name', 1, Type::string()),
        ]);

        $encoded = Cbor::encodeValue($userType, [
            'id' => 42,
            'name' => 'Maxim',
        ]);

        $this->assertSame([
            'id' => 42,
            'name' => 'Maxim',
        ], Cbor::decodeValue($userType, $encoded));
    }

    public function test_it_encodes_and_decodes_rpc_envelopes(): void
    {
        $userType = Type::struct([
            Field::value('id', 0, Type::int32()),
            Field::value('name', 1, Type::string()),
        ]);

        $descriptor = new MethodDescriptor('RenameUser', 1002, $userType, $userType);

        $envelope = Cbor::decodeEnvelope(Cbor::encodeEnvelope($descriptor, [
            'id' => 42,
            'name' => 'Maxim',
        ]));

        $this->assertSame('RenameUser', $envelope['method']);
        $this->assertSame([
            'id' => 42,
            'name' => 'Maxim',
        ], Cbor::decodeDenseValue($userType, $envelope['request']));
    }

    public function test_it_rejects_invalid_cbor_payloads(): void
    {
        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Invalid CBOR:');

        Cbor::decodeEnvelope('not-cbor');
    }
}
