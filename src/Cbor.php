<?php

declare(strict_types=1);

namespace Skir\Runtime;

use CBOR\Decoder;
use CBOR\Encoder;
use CBOR\StringStream;
use Skir\Runtime\Exceptions\SkirRuntimeException;
use Throwable;

final class Cbor
{
    public static function available(): bool
    {
        return class_exists(Encoder::class)
            && class_exists(Decoder::class)
            && class_exists(StringStream::class);
    }

    public static function encode(mixed $value): string
    {
        self::ensureAvailable();

        try {
            return (new Encoder)->encode($value);
        } catch (Throwable $exception) {
            throw SkirRuntimeException::invalidCbor($exception->getMessage(), $exception);
        }
    }

    public static function decode(string $content): mixed
    {
        self::ensureAvailable();

        try {
            return Decoder::create()
                ->decode(StringStream::create($content))
                ->normalize();
        } catch (Throwable $exception) {
            throw SkirRuntimeException::invalidCbor($exception->getMessage(), $exception);
        }
    }

    public static function encodeDenseValue(Type $type, mixed $value): string
    {
        return self::encode(self::encodeValuePayload($type, $value));
    }

    public static function decodeDenseValue(Type $type, mixed $value): mixed
    {
        return self::decodeValuePayload($type, $value);
    }

    public static function encodeValuePayload(Type $type, mixed $value): mixed
    {
        return DenseJson::encode($type, $value);
    }

    public static function decodeValuePayload(Type $type, mixed $value): mixed
    {
        return DenseJson::decode($type, $value);
    }

    public static function encodeValue(Type $type, mixed $value): string
    {
        return self::encodeDenseValue($type, $value);
    }

    public static function decodeValue(Type $type, string $content): mixed
    {
        return self::decodeDenseValue($type, self::decode($content));
    }

    public static function encodeEnvelope(MethodDescriptor $descriptor, mixed $request): string
    {
        return self::encode([
            'method' => $descriptor->name,
            'request' => self::encodeValuePayload($descriptor->requestType, $request),
        ]);
    }

    /**
     * @return array{
     *     method?: mixed,
     *     request?: mixed
     * }
     */
    public static function decodeEnvelope(string $content): array
    {
        $payload = self::decode($content);

        if (! is_array($payload)) {
            throw SkirRuntimeException::invalidCbor('Skir CBOR envelopes must be maps.');
        }

        return $payload;
    }

    private static function ensureAvailable(): void
    {
        if (self::available()) {
            return;
        }

        throw SkirRuntimeException::missingCborDependency();
    }
}
