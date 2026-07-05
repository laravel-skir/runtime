<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime;

use DateTimeInterface;
use JsonException;
use LaravelSkir\Runtime\Exceptions\SkirRuntimeException;

final class DenseJson
{
    private const int MAX_SAFE_JAVASCRIPT_INTEGER = 9_007_199_254_740_991;

    public static function toJson(Type $type, mixed $value): string
    {
        try {
            return json_encode(self::encode($type, $value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw SkirRuntimeException::invalidDenseJson($exception->getMessage(), $exception);
        }
    }

    public static function fromJson(Type $type, string $json): mixed
    {
        try {
            $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw SkirRuntimeException::invalidDenseJson($exception->getMessage(), $exception);
        }

        return self::decode($type, $value);
    }

    public static function encode(Type $type, mixed $value): mixed
    {
        return self::encodeValue($type, $value);
    }

    public static function decode(Type $type, mixed $value): mixed
    {
        return self::decodeValue($type, $value);
    }

    private static function encodeValue(Type $type, mixed $value): mixed
    {
        return match ($type->kind) {
            TypeKind::Bool => $value ? 1 : 0,
            TypeKind::Int32 => (int) $value,
            TypeKind::Int64, TypeKind::Hash64 => self::encodeLargeInteger($value),
            TypeKind::Float32, TypeKind::Float64 => self::encodeFloat((float) $value),
            TypeKind::Timestamp => self::encodeTimestamp($value),
            TypeKind::String => (string) $value,
            TypeKind::Bytes => base64_encode((string) $value),
            TypeKind::Optional => $value === null ? null : self::encodeValue(self::requireItemType($type), $value),
            TypeKind::Array => self::encodeArray($type, $value),
            TypeKind::Struct => self::encodeStruct($type, $value),
            TypeKind::Enum => self::encodeEnum($type, $value),
        };
    }

    private static function decodeValue(Type $type, mixed $value): mixed
    {
        if ($value === 0) {
            return self::decodeZero($type);
        }

        return match ($type->kind) {
            TypeKind::Bool => (bool) $value,
            TypeKind::Int32 => (int) $value,
            TypeKind::Int64, TypeKind::Hash64 => $value,
            TypeKind::Float32, TypeKind::Float64 => self::decodeFloat($value),
            TypeKind::Timestamp => (int) $value,
            TypeKind::String => (string) $value,
            TypeKind::Bytes => self::decodeBytes($value),
            TypeKind::Optional => $value === null ? null : self::decodeValue(self::requireItemType($type), $value),
            TypeKind::Array => self::decodeArray($type, $value),
            TypeKind::Struct => self::decodeStruct($type, $value),
            TypeKind::Enum => self::decodeEnum($type, $value),
        };
    }

    private static function encodeLargeInteger(mixed $value): int|string
    {
        if (is_int($value)) {
            return abs($value) <= self::MAX_SAFE_JAVASCRIPT_INTEGER ? $value : (string) $value;
        }

        if (is_string($value)) {
            return self::isSafeIntegerString($value) ? (int) $value : $value;
        }

        throw SkirRuntimeException::invalidValue('Skir int64/hash64 values must be integers or integer strings.');
    }

    private static function encodeFloat(float $value): float|string
    {
        if (is_nan($value)) {
            return 'NaN';
        }

        if ($value === INF) {
            return 'Infinity';
        }

        if ($value === -INF) {
            return '-Infinity';
        }

        return $value;
    }

    private static function decodeFloat(mixed $value): float
    {
        return match ($value) {
            'NaN' => NAN,
            'Infinity' => INF,
            '-Infinity' => -INF,
            default => (float) $value,
        };
    }

    private static function encodeTimestamp(mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return ((int) $value->format('U')) * 1000 + ((int) $value->format('v'));
        }

        return (int) $value;
    }

    private static function decodeBytes(mixed $value): string
    {
        $decoded = base64_decode((string) $value, true);

        if ($decoded === false) {
            throw SkirRuntimeException::invalidValue('Skir bytes values must be valid base64 strings.');
        }

        return $decoded;
    }

    private static function encodeArray(Type $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw SkirRuntimeException::invalidValue('Skir array values must be PHP arrays.');
        }

        $itemType = self::requireItemType($type);

        return array_map(
            fn (mixed $item): mixed => self::encodeValue($itemType, $item),
            array_values($value),
        );
    }

    private static function decodeArray(Type $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw SkirRuntimeException::invalidValue('Skir array JSON values must be arrays.');
        }

        $itemType = self::requireItemType($type);

        return array_map(
            fn (mixed $item): mixed => self::decodeValue($itemType, $item),
            array_values($value),
        );
    }

    private static function encodeStruct(Type $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw SkirRuntimeException::invalidValue('Skir struct values must be associative arrays.');
        }

        $encodedFields = [];

        foreach ($type->fields as $field) {
            $encodedFields[$field->number] = self::encodeField($field, $value);
        }

        ksort($encodedFields);

        if ($encodedFields !== []) {
            $lastNumber = max(array_keys($encodedFields));

            for ($number = 0; $number <= $lastNumber; $number++) {
                $encodedFields[$number] ??= 0;
            }

            ksort($encodedFields);
        }

        while ($encodedFields !== []) {
            $lastNumber = array_key_last($encodedFields);
            $field = self::fieldWithNumber($type, $lastNumber);
            $encodedDefault = $field === null
                ? 0
                : self::encodedDefaultForField($field);

            if ($encodedFields[$lastNumber] !== $encodedDefault) {
                break;
            }

            unset($encodedFields[$lastNumber]);
        }

        return array_values($encodedFields);
    }

    private static function decodeStruct(Type $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw SkirRuntimeException::invalidValue('Skir struct JSON values must be arrays.');
        }

        $decoded = [];

        foreach ($type->fields as $field) {
            if ($field->removed) {
                continue;
            }

            $fieldValue = array_key_exists($field->number, $value)
                ? $value[$field->number]
                : self::defaultValue(self::requireFieldType($field));

            $decoded[$field->name] = array_key_exists($field->number, $value)
                ? self::decodeValue(self::requireFieldType($field), $fieldValue)
                : $fieldValue;
        }

        return $decoded;
    }

    private static function encodeField(Field $field, array $value): mixed
    {
        if ($field->removed) {
            return 0;
        }

        $fieldType = self::requireFieldType($field);
        $fieldValue = array_key_exists($field->name, $value)
            ? $value[$field->name]
            : self::defaultValue($fieldType);

        return self::encodeValue($fieldType, $fieldValue);
    }

    private static function encodedDefaultForField(Field $field): mixed
    {
        if ($field->removed) {
            return 0;
        }

        $fieldType = self::requireFieldType($field);

        return self::encodeValue($fieldType, self::defaultValue($fieldType));
    }

    private static function encodeEnum(Type $type, mixed $value): int|array
    {
        if (! $value instanceof EnumValue) {
            throw SkirRuntimeException::invalidValue('Skir enum values must be EnumValue instances.');
        }

        if ($value->name === 'UNKNOWN') {
            return 0;
        }

        $variant = self::variantWithName($type, $value->name);

        if (! $value->wrapper) {
            return $variant->number;
        }

        if (! $variant->isWrapper()) {
            throw SkirRuntimeException::invalidValue("Skir enum variant [{$value->name}] does not carry a payload.");
        }

        return [
            $variant->number,
            self::encodeValue($variant->payloadType, $value->value),
        ];
    }

    private static function decodeEnum(Type $type, mixed $value): EnumValue
    {
        if ($value === 0) {
            return EnumValue::constant('UNKNOWN');
        }

        if (is_int($value)) {
            return EnumValue::constant(self::variantWithNumber($type, $value)->name);
        }

        if (! is_array($value)) {
            throw SkirRuntimeException::invalidValue('Skir enum JSON values must be integers or wrapper arrays.');
        }

        $variant = self::variantWithNumber($type, (int) $value[0]);

        if (! $variant->isWrapper()) {
            throw SkirRuntimeException::invalidValue("Skir enum variant [{$variant->name}] does not carry a payload.");
        }

        return EnumValue::wrapper($variant->name, self::decodeValue($variant->payloadType, $value[1]));
    }

    private static function decodeZero(Type $type): mixed
    {
        if ($type->kind === TypeKind::Optional) {
            return self::decodeValue(self::requireItemType($type), 0);
        }

        return self::defaultValue($type);
    }

    private static function defaultValue(Type $type): mixed
    {
        return match ($type->kind) {
            TypeKind::Bool => false,
            TypeKind::Int32, TypeKind::Int64, TypeKind::Hash64, TypeKind::Timestamp => 0,
            TypeKind::Float32, TypeKind::Float64 => 0.0,
            TypeKind::String, TypeKind::Bytes => '',
            TypeKind::Optional => null,
            TypeKind::Array => [],
            TypeKind::Struct => self::defaultStructValue($type),
            TypeKind::Enum => EnumValue::constant('UNKNOWN'),
        };
    }

    private static function defaultStructValue(Type $type): array
    {
        $defaults = [];

        foreach ($type->fields as $field) {
            if ($field->removed) {
                continue;
            }

            $defaults[$field->name] = self::defaultValue(self::requireFieldType($field));
        }

        return $defaults;
    }

    private static function requireItemType(Type $type): Type
    {
        if ($type->itemType === null) {
            throw SkirRuntimeException::invalidValue("Skir [{$type->kind->value}] type does not define an item type.");
        }

        return $type->itemType;
    }

    private static function requireFieldType(Field $field): Type
    {
        if ($field->type === null) {
            throw SkirRuntimeException::invalidValue("Skir field [{$field->number}] does not define a type.");
        }

        return $field->type;
    }

    private static function fieldWithNumber(Type $type, int $number): ?Field
    {
        foreach ($type->fields as $field) {
            if ($field->number === $number) {
                return $field;
            }
        }

        return null;
    }

    private static function variantWithName(Type $type, string $name): Variant
    {
        foreach ($type->variants as $variant) {
            if ($variant->name === $name) {
                return $variant;
            }
        }

        throw SkirRuntimeException::invalidValue("Skir enum variant [{$name}] is not defined.");
    }

    private static function variantWithNumber(Type $type, int $number): Variant
    {
        foreach ($type->variants as $variant) {
            if ($variant->number === $number) {
                return $variant;
            }
        }

        throw SkirRuntimeException::invalidValue("Skir enum variant [{$number}] is not defined.");
    }

    private static function isSafeIntegerString(string $value): bool
    {
        if (! preg_match('/^-?\d+$/', $value)) {
            throw SkirRuntimeException::invalidValue('Skir int64/hash64 string values must contain only digits.');
        }

        $integer = (int) $value;

        return (string) $integer === $value && abs($integer) <= self::MAX_SAFE_JAVASCRIPT_INTEGER;
    }
}
