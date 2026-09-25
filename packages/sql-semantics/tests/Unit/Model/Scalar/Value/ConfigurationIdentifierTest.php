<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ConfigurationIdentifier::class)]
#[Medium]
final class ConfigurationIdentifierTest extends TestCase
{
    public function testInputsHasNoOperandsForABareSettingValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET search_path = public, pg_catalog');
        self::assertInstanceOf(SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(AssignedSetting::class, $setting);
        $value = $setting->values[0];
        self::assertInstanceOf(ConfigurationIdentifier::class, $value);
        self::assertSame(['public'], $value->name);
        self::assertSame([], $value->inputs());
        self::assertSame('text', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame('SET "search_path" = "public", "pg_catalog"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testSpellingJoinsTheNameParts(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ConfigurationIdentifier($origin->facts, $origin->source, ['pg_catalog', 'english']);
        self::assertSame('pg_catalog.english', $value->spelling());
        self::assertSame('"pg_catalog"."english"', $value->structure()->toString());
    }

    public function testRejectsAnEmptyName(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ConfigurationIdentifier($origin->facts, $origin->source, []);
    }

    public function testWithFactsKeepsTheNameAndLeavesTheOriginalUnchanged(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ConfigurationIdentifier($origin->facts, $origin->source, ['public']);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame(['public'], $copy->name);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }
}
