<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\Normalization;
use SqlSemantics\Model\Scalar\Text\UnicodeNormalForm;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Normalization::class)]
#[Medium]
final class NormalizationTest extends TestCase
{
    public function testInputsIsTheNormalizedString(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT NORMALIZE('a', NFKD), NORMALIZE('b')");
        self::assertInstanceOf(BoundSelect::class, $query);
        [$explicit, $implicit] = array_map(static fn ($output) => $output->expression, $query->outputs);
        self::assertInstanceOf(Normalization::class, $explicit);
        self::assertInstanceOf(Normalization::class, $implicit);
        self::assertSame(UnicodeNormalForm::Nfkd, $explicit->form);
        self::assertSame(UnicodeNormalForm::Nfc, $implicit->form);
        self::assertSame([$explicit->string], $explicit->inputs());
        self::assertSame('text', $explicit->type->name);
        self::assertSame(Nullability::NotNull, $explicit->nullability);
        self::assertSame("SELECT NORMALIZE('a', NFKD), NORMALIZE('b', NFC)", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testInputsRejectsMySql(): void
    {
        $string = Expression::literal('a', Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new Normalization($string->source, $string);
    }

    public function testSpellingIdentifiesNormalize(): void
    {
        $string = Expression::literal(null, Dialect::PostgreSql);
        $normalization = new Normalization($string->source, $string);
        self::assertSame('NORMALIZE', $normalization->spelling());
        self::assertSame(Nullability::AlwaysNull, $normalization->nullability);
    }

    public function testWithFactsPreservesTheForm(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $normalization = new Normalization($string->source, $string, UnicodeNormalForm::Nfd);
        $copy = $normalization->withFacts($normalization->facts);
        self::assertNotSame($normalization, $copy);
        self::assertSame(UnicodeNormalForm::Nfd, $copy->form);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $normalization = new Normalization($string->source, $string);
        $this->expectException(InvalidStructure::class);
        $normalization->withFacts($string->facts);
    }
}
