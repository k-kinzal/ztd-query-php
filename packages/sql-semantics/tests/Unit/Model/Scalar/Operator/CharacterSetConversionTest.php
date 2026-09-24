<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Operator\CharacterSetConversion;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CharacterSetConversion::class)]
#[Medium]
final class CharacterSetConversionTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'utf8', 'longtext'])]
    #[TestWith(['mysql-5.7.44', 'latin1', 'longtext'])]
    #[TestWith(['mysql-8.0.44', 'BINARY', 'longblob'])]
    #[TestWith(['mysql-8.4.7', 'utf8mb4', 'longtext'])]
    #[TestWith(['mysql-9.1.0', 'binary', 'longblob'])]
    public function testInputsKeepTheConvertedString(string $version, string $characterSet, string $type): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind("SELECT CONVERT('abc' USING " . $characterSet . ')');
        self::assertInstanceOf(BoundSelect::class, $query);
        $conversion = $query->outputs[0]->expression;
        self::assertInstanceOf(CharacterSetConversion::class, $conversion);
        self::assertSame(strtolower($characterSet), $conversion->characterSet);
        self::assertSame([$conversion->operand], $conversion->inputs());
        self::assertSame($type, $conversion->type->name);
        self::assertSame("SELECT CONVERT('abc' USING `" . strtolower($characterSet) . '`)', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnUppercaseCharacterSetName(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new CharacterSetConversion($value->source, $value, 'UTF8');
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $value = Expression::literal('a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new CharacterSetConversion($value->source, $value, 'utf8');
    }

    public function testSpellingNamesTheConversion(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        self::assertSame('CONVERT', (new CharacterSetConversion($value->source, $value, 'utf8'))->spelling());
    }

    public function testWithFactsPreservesTheCharacterSet(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $conversion = new CharacterSetConversion($value->source, $value, 'utf8');
        $copy = $conversion->withFacts($conversion->facts);
        self::assertNotSame($conversion, $copy);
        self::assertSame('utf8', $copy->characterSet);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $value = Expression::literal('a', Dialect::MySql);
        $conversion = new CharacterSetConversion($value->source, $value, 'utf8');
        $this->expectException(InvalidStructure::class);
        $conversion->withFacts($value->facts);
    }
}
