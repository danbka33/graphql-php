<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\SerializationError;
use GraphQL\GraphQL;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Tests\Type\PhpEnumType\DocBlockPhpEnum;
use GraphQL\Tests\Type\PhpEnumType\IntPhpEnum;
use GraphQL\Tests\Type\PhpEnumType\MultipleDeprecationsPhpEnum;
use GraphQL\Tests\Type\PhpEnumType\MultipleDescriptionsCasePhpEnum;
use GraphQL\Tests\Type\PhpEnumType\MultipleDescriptionsPhpEnum;
use GraphQL\Tests\Type\PhpEnumType\PhpEnum;
use GraphQL\Tests\Type\PhpEnumType\StringPhpEnum;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\Value;

final class PhpEnumTypeTest extends TestCaseBase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (version_compare(phpversion(), '8.1', '<')) {
            self::markTestSkipped('Native PHP enums are only available with PHP 8.1');
        }
    }

    public function testConstructEnumTypeFromPhpEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        self::assertSame(<<<'GRAPHQL'
"foo"
enum PhpEnum {
  "bar"
  A
  B @deprecated
  C @deprecated(reason: "baz")
}
GRAPHQL, SchemaPrinter::printType($enumType));
    }

    public function testConstructEnumTypeFromIntPhpEnum(): void
    {
        $enumType = new PhpEnumType(IntPhpEnum::class);
        self::assertSame(<<<'GRAPHQL'
enum IntPhpEnum {
  A
}
GRAPHQL, SchemaPrinter::printType($enumType));
    }

    public function testConstructEnumTypeFromStringPhpEnum(): void
    {
        $enumType = new PhpEnumType(StringPhpEnum::class);
        self::assertSame(<<<'GRAPHQL'
enum StringPhpEnum {
  A
  B
}
GRAPHQL, SchemaPrinter::printType($enumType));
    }

    public function testConstructEnumTypeFromPhpEnumWithCustomName(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class, 'CustomNamedPhpEnum');
        self::assertSame(<<<'GRAPHQL'
"foo"
enum CustomNamedPhpEnum {
  "bar"
  A
  B @deprecated
  C @deprecated(reason: "baz")
}
GRAPHQL, SchemaPrinter::printType($enumType));
    }

    public function testConstructEnumTypeFromPhpEnumWithDocBlockDescriptions(): void
    {
        $enumType = new PhpEnumType(DocBlockPhpEnum::class);
        self::assertSame(<<<'GRAPHQL'
"foo"
enum DocBlockPhpEnum {
  "preferred"
  A

  """
  multi
  line.
  """
  B
}
GRAPHQL, SchemaPrinter::printType($enumType));
    }

    public function testMultipleDescriptionsDisallowed(): void
    {
        self::expectExceptionObject(new \Exception(PhpEnumType::MULTIPLE_DESCRIPTIONS_DISALLOWED));
        new PhpEnumType(MultipleDescriptionsPhpEnum::class);
    }

    public function testMultipleDescriptionsDisallowedOnCase(): void
    {
        self::expectExceptionObject(new \Exception(PhpEnumType::MULTIPLE_DESCRIPTIONS_DISALLOWED));
        new PhpEnumType(MultipleDescriptionsCasePhpEnum::class);
    }

    public function testMultipleDeprecationsDisallowed(): void
    {
        self::expectExceptionObject(new \Exception(PhpEnumType::MULTIPLE_DEPRECATIONS_DISALLOWED));
        new PhpEnumType(MultipleDeprecationsPhpEnum::class);
    }

    public function testParseValueOfEnumSimpleEnum(): void
    {
        $intEnum = new PhpEnumType(PhpEnum::class);

        $result = Value::coerceInputValue(PhpEnum::C, $intEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(PhpEnum::C, $result['value']);
    }

    public function testParseValueOfStringSimpleEnum(): void
    {
        $intEnum = new PhpEnumType(PhpEnum::class);

        $result = Value::coerceInputValue('C', $intEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(PhpEnum::C, $result['value']);
    }

    public function testParseValueOfEnumTypeIntEnum(): void
    {
        $intEnum = new PhpEnumType(IntPhpEnum::class);

        $result = Value::coerceInputValue(IntPhpEnum::A, $intEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(IntPhpEnum::A, $result['value']);
    }

    public function testParseValueOfStringTypeIntEnum(): void
    {
        $intEnum = new PhpEnumType(IntPhpEnum::class);

        $result = Value::coerceInputValue('A', $intEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(IntPhpEnum::A, $result['value']);
    }

    public function testParseValueOfEnumTypeStringEnum(): void
    {
        $stringEnum = new PhpEnumType(StringPhpEnum::class);

        $result = Value::coerceInputValue(StringPhpEnum::B, $stringEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(StringPhpEnum::B, $result['value']);
    }

    public function testParseValueOfStringTypeStringEnum(): void
    {
        $stringEnum = new PhpEnumType(StringPhpEnum::class);

        $result = Value::coerceInputValue('A', $stringEnum);

        self::assertIsArray($result);
        self::assertNull($result['errors']);
        self::assertSame(StringPhpEnum::A, $result['value']);
    }

    public function testExecutesSubscriptionWithEnumTypeFromPhpEnum(): void
    {
        $enumType = new PhpEnumType(StringPhpEnum::class);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => []
            ]),
            'subscription' => new ObjectType([
                'name' => 'Subscription',
                'fields' => [
                    'foo' => [
                        'type' => Type::nonNull($enumType),
                        'args' => [
                            'bar' => [
                                'type' => Type::nonNull($enumType),
                            ],
                        ],
                        'resolve' => static function ($_, array $args): StringPhpEnum {
                            $bar = $args['bar'];

                            assert($bar === StringPhpEnum::B);

                            return $bar;
                        },
                    ],
                ],
            ]),
        ]);

        self::assertSame([
            'data' => [
                'foo' => 'B',
            ],
        ], GraphQL::executeQuery($schema, 'subscription { foo(bar: B) }')->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE));
    }

    public function testExecutesWithEnumTypeFromPhpEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => Type::nonNull($enumType),
                        'args' => [
                            'bar' => [
                                'type' => Type::nonNull($enumType),
                            ],
                        ],
                        'resolve' => static function ($_, array $args): PhpEnum {
                            $bar = $args['bar'];
                            assert($bar === PhpEnum::A);

                            return $bar;
                        },
                    ],
                ],
            ]),
        ]);

        self::assertSame([
            'data' => [
                'foo' => 'A',
            ],
        ], GraphQL::executeQuery($schema, '{ foo(bar: A) }')->toArray());
    }

    public function testFailsToSerializeNonEnum(): void
    {
        $enumType = new PhpEnumType(PhpEnum::class);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'foo' => [
                        'type' => Type::nonNull($enumType),
                        'resolve' => static fn (): string => 'A',
                    ],
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ foo }');

        self::expectExceptionObject(new SerializationError('Cannot serialize value as enum: "A", expected instance of GraphQL\\Tests\\Type\\PhpEnumType\\PhpEnum.'));
        $result->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);
    }
}
