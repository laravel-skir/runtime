<?php

declare(strict_types=1);

namespace LaravelSkir\Runtime\Tests;

use LaravelSkir\Runtime\DenseJson;
use LaravelSkir\Runtime\EnumValue;
use LaravelSkir\Runtime\Field;
use LaravelSkir\Runtime\Type;
use LaravelSkir\Runtime\Variant;
use PHPUnit\Framework\TestCase;

final class DenseJsonTest extends TestCase
{
    public function test_it_encodes_primitive_values(): void
    {
        $this->assertSame('1', DenseJson::toJson(Type::bool(), true));
        $this->assertSame('0', DenseJson::toJson(Type::bool(), false));
        $this->assertSame('1234', DenseJson::toJson(Type::int32(), 1234));
        $this->assertSame('"9223372036854775807"', DenseJson::toJson(Type::int64(), '9223372036854775807'));
        $this->assertSame('"18446744073709551615"', DenseJson::toJson(Type::hash64(), '18446744073709551615'));
        $this->assertSame('1743682787000', DenseJson::toJson(Type::timestamp(), 1743682787000));
        $this->assertSame('"Infinity"', DenseJson::toJson(Type::float64(), INF));
        $this->assertSame('"Hello"', DenseJson::toJson(Type::string(), 'Hello'));
        $this->assertSame('"AQID"', DenseJson::toJson(Type::bytes(), "\x01\x02\x03"));
    }

    public function test_it_encodes_structs_as_field_number_indexed_arrays(): void
    {
        $user = Type::struct([
            Field::value('user_id', 0, Type::int32()),
            Field::removed(1),
            Field::value('name', 2, Type::string()),
            Field::value('nickname', 3, Type::string()),
        ]);

        $json = DenseJson::toJson($user, [
            'user_id' => 400,
            'name' => 'John Doe',
        ]);

        $this->assertSame('[400,0,"John Doe"]', $json);
    }

    public function test_it_decodes_structs_with_missing_trailing_fields(): void
    {
        $user = Type::struct([
            Field::value('user_id', 0, Type::int32()),
            Field::removed(1),
            Field::value('name', 2, Type::string()),
            Field::value('nickname', 3, Type::string()),
        ]);

        $decoded = DenseJson::fromJson($user, '[400,0,"John Doe"]');

        $this->assertSame([
            'user_id' => 400,
            'name' => 'John Doe',
            'nickname' => '',
        ], $decoded);
    }

    public function test_it_encodes_and_decodes_enum_variants(): void
    {
        $subscriptionStatus = Type::enum([
            Variant::constant('free', 1),
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->assertSame('1', DenseJson::toJson($subscriptionStatus, EnumValue::constant('free')));
        $this->assertSame('[2,1743682787000]', DenseJson::toJson(
            $subscriptionStatus,
            EnumValue::wrapper('premium_since', 1743682787000),
        ));

        $this->assertEquals(
            EnumValue::wrapper('premium_since', 1743682787000),
            DenseJson::fromJson($subscriptionStatus, '[2,1743682787000]'),
        );
    }

    public function test_it_decodes_zero_as_default_for_forward_compatibility(): void
    {
        $this->assertSame('', DenseJson::fromJson(Type::string(), '0'));
        $this->assertSame([], DenseJson::fromJson(Type::array(Type::string()), '0'));
        $this->assertSame('', DenseJson::fromJson(Type::optional(Type::string()), '0'));
        $this->assertNull(DenseJson::fromJson(Type::optional(Type::string()), 'null'));
    }
}
