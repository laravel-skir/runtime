<?php

declare(strict_types=1);

namespace Skir\Runtime\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skir\Runtime\DenseJson;
use Skir\Runtime\EnumValue;
use Skir\Runtime\Exceptions\SkirRuntimeException;
use Skir\Runtime\Field;
use Skir\Runtime\Type;
use Skir\Runtime\Variant;

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

    public function test_it_preserves_permissive_primitive_wire_coercion(): void
    {
        $this->assertTrue(DenseJson::fromJson(Type::bool(), '"false"'));
        $this->assertSame(0, DenseJson::fromJson(Type::int32(), '"not-a-number"'));
        $this->assertSame('9223372036854775807', DenseJson::fromJson(Type::int64(), '"9223372036854775807"'));
        $this->assertSame(2.5, DenseJson::fromJson(Type::float64(), '"2.5"'));
        $this->assertSame(1743682787000, DenseJson::fromJson(Type::timestamp(), '"1743682787000"'));
        $this->assertSame("\x01\x02\x03", DenseJson::fromJson(Type::bytes(), '"AQID"'));
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

    public function test_it_preserves_sparse_field_numbers_when_encoding_structs(): void
    {
        $settings = Type::struct([
            Field::value('name', 2, Type::string()),
            Field::value('enabled', 5, Type::bool()),
        ]);

        $json = DenseJson::toJson($settings, [
            'name' => 'notifications',
            'enabled' => true,
        ]);

        $this->assertSame('[0,0,"notifications",0,0,1]', $json);
    }

    public function test_it_trims_trailing_defaults_from_sparse_structs(): void
    {
        $settings = Type::struct([
            Field::value('name', 2, Type::string()),
            Field::value('enabled', 5, Type::bool()),
        ]);

        $json = DenseJson::toJson($settings, [
            'name' => 'notifications',
            'enabled' => false,
        ]);

        $this->assertSame('[0,0,"notifications"]', $json);
    }

    public function test_it_preserves_explicit_null_optional_fields_before_later_values(): void
    {
        $address = Type::struct([
            Field::value('city', 0, Type::string()),
        ]);
        $profile = Type::struct([
            Field::value('addresses', 0, Type::optional(Type::array($address))),
            Field::value('nickname', 1, Type::optional(Type::string())),
            Field::value('address', 2, Type::optional($address)),
            Field::value('name', 4, Type::string()),
        ]);
        $value = [
            'addresses' => null,
            'nickname' => null,
            'address' => null,
            'name' => 'John Doe',
        ];

        $json = DenseJson::toJson($profile, $value);

        $this->assertSame('[null,null,null,0,"John Doe"]', $json);
        $this->assertSame($value, DenseJson::fromJson($profile, $json));
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

    #[DataProvider('compatibleEnumWrapperVariantNumbers')]
    public function test_it_normalizes_integer_equivalent_enum_wrapper_variant_numbers(string $json): void
    {
        $subscriptionStatus = Type::enum([
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->assertEquals(
            EnumValue::wrapper('premium_since', 1743682787000),
            DenseJson::fromJson($subscriptionStatus, $json),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function compatibleEnumWrapperVariantNumbers(): iterable
    {
        yield 'numeric string' => ['["2",1743682787000]'];
        yield 'integral float' => ['[2.0,1743682787000]'];
    }

    #[DataProvider('compatibleScalarEnumVariantNumbers')]
    public function test_it_normalizes_integer_equivalent_scalar_enum_variant_numbers(string $json): void
    {
        $subscriptionStatus = Type::enum([
            Variant::constant('free', 1),
        ]);

        $this->assertEquals(
            EnumValue::constant('free'),
            DenseJson::fromJson($subscriptionStatus, $json),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function compatibleScalarEnumVariantNumbers(): iterable
    {
        yield 'numeric string' => ['"1"'];
        yield 'integral float' => ['1.0'];
    }

    #[DataProvider('compatibleUnknownEnumVariantNumbers')]
    public function test_it_preserves_unknown_for_integer_equivalent_zero_values(string $json): void
    {
        $subscriptionStatus = Type::enum([
            Variant::constant('free', 1),
        ]);

        $this->assertEquals(
            EnumValue::constant('UNKNOWN'),
            DenseJson::fromJson($subscriptionStatus, $json),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function compatibleUnknownEnumVariantNumbers(): iterable
    {
        yield 'numeric string zero' => ['"0"'];
        yield 'integral float zero' => ['0.0'];
    }

    #[DataProvider('invalidScalarEnumVariantNumbers')]
    public function test_it_rejects_invalid_scalar_enum_variant_numbers(string $json): void
    {
        $subscriptionStatus = Type::enum([
            Variant::constant('free', 1),
        ]);

        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Skir enum variant numbers must be safe integer-equivalent values.');

        DenseJson::fromJson($subscriptionStatus, $json);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidScalarEnumVariantNumbers(): iterable
    {
        yield 'nonnumeric string' => ['"premium_since"'];
        yield 'non-integral float' => ['1.5'];
        yield 'non-finite float' => ['1e309'];
        yield 'out-of-range integer' => ['9007199254740992'];
        yield 'out-of-range string' => ['"9007199254740992"'];
        yield 'noncanonical numeric string' => ['"1.0"'];
    }

    public function test_it_does_not_extend_scalar_normalization_to_wrapper_variants(): void
    {
        $subscriptionStatus = Type::enum([
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Skir enum wrapper variant [premium_since] must be encoded as a wrapper array.');

        DenseJson::fromJson($subscriptionStatus, '"2"');
    }

    public function test_it_preserves_native_integer_scalar_wrapper_variant_behavior(): void
    {
        $subscriptionStatus = Type::enum([
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->assertEquals(
            EnumValue::constant('premium_since'),
            DenseJson::fromJson($subscriptionStatus, '2'),
        );
    }

    public function test_it_decodes_numeric_key_json_objects_as_enum_wrapper_tuples(): void
    {
        $subscriptionStatus = Type::enum([
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->assertEquals(
            EnumValue::wrapper('premium_since', 1743682787000),
            DenseJson::fromJson($subscriptionStatus, '{"0":2,"1":1743682787000}'),
        );
    }

    public function test_it_rejects_out_of_range_float_variant_numbers_before_integer_casting(): void
    {
        $subscriptionStatus = Type::enum([
            Variant::wrapper('premium_since', 4096, Type::timestamp()),
        ]);

        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Skir enum variant numbers must be safe integer-equivalent values.');

        DenseJson::fromJson($subscriptionStatus, '[18446744073709555712,1743682787000]');
    }

    #[DataProvider('malformedEnumWrappers')]
    public function test_it_rejects_malformed_enum_wrappers(string $json, string $message): void
    {
        $subscriptionStatus = Type::enum([
            Variant::constant('free', 1),
            Variant::wrapper('premium_since', 2, Type::timestamp()),
        ]);

        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage($message);

        DenseJson::fromJson($subscriptionStatus, $json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedEnumWrappers(): iterable
    {
        yield 'empty tuple' => ['[]', 'Skir enum wrapper JSON values must contain exactly a variant number and payload.'];
        yield 'missing payload' => ['[2]', 'Skir enum wrapper JSON values must contain exactly a variant number and payload.'];
        yield 'excess tuple element' => ['[2,1743682787000,"extra"]', 'Skir enum wrapper JSON values must contain exactly a variant number and payload.'];
        yield 'named-key object' => ['{"variant":2,"payload":1743682787000}', 'Skir enum wrapper JSON values must contain exactly a variant number and payload.'];
        yield 'sparse numeric-key object' => ['{"0":2,"2":1743682787000}', 'Skir enum wrapper JSON values must contain exactly a variant number and payload.'];
        yield 'non-integral variant number' => ['[2.5,1743682787000]', 'Skir enum variant numbers must be safe integer-equivalent values.'];
        yield 'non-finite variant number' => ['[1e309,1743682787000]', 'Skir enum variant numbers must be safe integer-equivalent values.'];
        yield 'out-of-range integer variant number' => ['[9007199254740992,1743682787000]', 'Skir enum variant numbers must be safe integer-equivalent values.'];
        yield 'out-of-range string variant number' => ['["9007199254740992",1743682787000]', 'Skir enum variant numbers must be safe integer-equivalent values.'];
        yield 'noncanonical numeric string' => ['["2.0",1743682787000]', 'Skir enum variant numbers must be safe integer-equivalent values.'];
        yield 'unknown variant number' => ['[99,1743682787000]', 'Skir enum variant [99] is not defined.'];
        yield 'constant variant with payload' => ['[1,1743682787000]', 'Skir enum variant [free] does not carry a payload.'];
    }

    public function test_it_decodes_zero_as_default_for_forward_compatibility(): void
    {
        $this->assertSame('', DenseJson::fromJson(Type::string(), '0'));
        $this->assertSame([], DenseJson::fromJson(Type::array(Type::string()), '0'));
        $this->assertSame('', DenseJson::fromJson(Type::optional(Type::string()), '0'));
        $this->assertNull(DenseJson::fromJson(Type::optional(Type::string()), 'null'));
    }

    public function test_it_round_trips_nested_structs_arrays_and_optionals(): void
    {
        $address = Type::struct([
            Field::value('city', 0, Type::string()),
            Field::value('postal_codes', 1, Type::array(Type::string())),
        ]);

        $user = Type::struct([
            Field::value('name', 0, Type::string()),
            Field::value('address', 1, $address),
            Field::value('tags', 2, Type::array(Type::string())),
            Field::value('nickname', 3, Type::optional(Type::string())),
        ]);

        $value = [
            'name' => 'John Doe',
            'address' => [
                'city' => 'Antwerp',
                'postal_codes' => ['2000', '2018'],
            ],
            'tags' => ['admin', 'beta'],
            'nickname' => 'johnny',
        ];

        $json = DenseJson::toJson($user, $value);

        $this->assertSame('["John Doe",["Antwerp",["2000","2018"]],["admin","beta"],"johnny"]', $json);
        $this->assertSame($value, DenseJson::fromJson($user, $json));
    }

    public function test_it_decodes_missing_nested_struct_fields_to_defaults(): void
    {
        $address = Type::struct([
            Field::value('city', 0, Type::string()),
            Field::value('postal_codes', 1, Type::array(Type::string())),
        ]);

        $user = Type::struct([
            Field::value('name', 0, Type::string()),
            Field::value('address', 1, $address),
        ]);

        $this->assertSame([
            'name' => 'John Doe',
            'address' => [
                'city' => '',
                'postal_codes' => [],
            ],
        ], DenseJson::fromJson($user, '["John Doe"]'));
    }

    public function test_it_rejects_invalid_dense_json_values(): void
    {
        $this->expectException(SkirRuntimeException::class);
        $this->expectExceptionMessage('Skir array JSON values must be arrays.');

        DenseJson::fromJson(Type::array(Type::string()), '"not-array"');
    }
}
