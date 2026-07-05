<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime;

final readonly class Type
{
    /**
     * @param  array<int, Field>  $fields
     * @param  array<int, Variant>  $variants
     */
    private function __construct(
        public TypeKind $kind,
        public ?Type $itemType = null,
        public array $fields = [],
        public array $variants = [],
    ) {}

    public static function bool(): self
    {
        return new self(TypeKind::Bool);
    }

    public static function int32(): self
    {
        return new self(TypeKind::Int32);
    }

    public static function int64(): self
    {
        return new self(TypeKind::Int64);
    }

    public static function hash64(): self
    {
        return new self(TypeKind::Hash64);
    }

    public static function float32(): self
    {
        return new self(TypeKind::Float32);
    }

    public static function float64(): self
    {
        return new self(TypeKind::Float64);
    }

    public static function timestamp(): self
    {
        return new self(TypeKind::Timestamp);
    }

    public static function string(): self
    {
        return new self(TypeKind::String);
    }

    public static function bytes(): self
    {
        return new self(TypeKind::Bytes);
    }

    public static function optional(Type $itemType): self
    {
        return new self(TypeKind::Optional, itemType: $itemType);
    }

    public static function array(Type $itemType): self
    {
        return new self(TypeKind::Array, itemType: $itemType);
    }

    /**
     * @param  array<int, Field>  $fields
     */
    public static function struct(array $fields): self
    {
        usort($fields, fn (Field $left, Field $right): int => $left->number <=> $right->number);

        return new self(TypeKind::Struct, fields: $fields);
    }

    /**
     * @param  array<int, Variant>  $variants
     */
    public static function enum(array $variants): self
    {
        usort($variants, fn (Variant $left, Variant $right): int => $left->number <=> $right->number);

        return new self(TypeKind::Enum, variants: $variants);
    }
}
