<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ConfigurationKeyword;
use SqlSemantics\Model\Scalar\Value\SettingKeyword;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ConfigurationKeyword::class)]
#[Medium]
final class ConfigurationKeywordTest extends TestCase
{
    #[TestWith(['SET xmloption = DOCUMENT', SettingKeyword::Document, 'SET "xmloption" = DOCUMENT'])]
    #[TestWith(['SET default_transaction_read_only = ON', SettingKeyword::On, 'SET "default_transaction_read_only" = ON'])]
    #[TestWith(['SET timezone = LOCAL', SettingKeyword::Local, 'SET "timezone" = LOCAL'])]
    public function testInputsHasNoOperandsForAKeywordSettingValue(string $sql, SettingKeyword $keyword, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(AssignedSetting::class, $setting);
        $value = $setting->values[0];
        self::assertInstanceOf(ConfigurationKeyword::class, $value);
        self::assertSame($keyword, $value->keyword);
        self::assertSame([], $value->inputs());
        self::assertSame($serialized, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($serialized, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($serialized)));
    }

    public function testSpellingReturnsTheKeywordText(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ConfigurationKeyword($origin->facts, $origin->source, SettingKeyword::ReadCommitted);
        self::assertSame('READ COMMITTED', $value->spelling());
        self::assertSame('READ COMMITTED', $value->structure()->toString());
    }

    public function testWithFactsKeepsTheKeywordAndLeavesTheOriginalUnchanged(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ConfigurationKeyword($origin->facts, $origin->source, SettingKeyword::NotDeferrable);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame(SettingKeyword::NotDeferrable, $copy->keyword);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }
}
