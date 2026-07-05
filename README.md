# Laravel Skir Runtime

Framework-agnostic PHP runtime support for Skir serialization and descriptors.

This package is intentionally small. Laravel-specific routing and client behavior live in separate packages.

## Installation

```bash
composer require laravel-skir/runtime
```

## What it provides

- `DenseJson` for encoding and decoding Skir dense JSON values.
- `Type`, `Field`, and `Variant` descriptors for generated DTO metadata.
- `EnumValue` for Skir enum constants and wrapper variants.
- `MethodDescriptor` for generated SkirRPC method metadata.
- Package-local `SkirRuntimeException` exceptions for invalid Skir values.

## Dense JSON

```php
use LaravelSkir\Runtime\DenseJson;
use LaravelSkir\Runtime\Field;
use LaravelSkir\Runtime\Type;

$user = Type::struct([
    Field::value('id', 0, Type::int32()),
    Field::value('name', 1, Type::string()),
]);

$json = DenseJson::toJson($user, [
    'id' => 1,
    'name' => 'Jane',
]);

$data = DenseJson::fromJson($user, $json);
```

Struct values are encoded as field-number indexed arrays. Removed fields and sparse field numbers are preserved so generated DTOs can stay compatible with existing Skir schemas.

## Current scope

This runtime does not register routes, discover procedures, or make HTTP calls. Those concerns belong in the `laravel-skir/server` and `laravel-skir/client` packages.
