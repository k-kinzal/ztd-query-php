<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\NormalizedPredicate;
use SqlSemantics\Model\Scalar\Text\UnicodeNormalForm;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NormalizedPredicate::class)]
#[Medium]
final class NormalizedPredicateTest extends TestCase
{
    #[TestWith(["SELECT 'a' IS NORMALIZED", UnicodeNormalForm::Nfc, false, "SELECT (('a') IS NFC NORMALIZED)"])]
    #[TestWith(["SELECT 'a' IS NOT NFKC NORMALIZED", UnicodeNormalForm::Nfkc, true, "SELECT (('a') IS NOT NFKC NORMALIZED)"])]
    public function testInputsIsTheTestedString(string $sql, UnicodeNormalForm $form, bool $negated, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $predicate = $query->outputs[0]->expression;
        self::assertInstanceOf(NormalizedPredicate::class, $predicate);
        self::assertSame($form, $predicate->form);
        self::assertSame($negated, $predicate->negated);
        self::assertSame([$predicate->string], $predicate->inputs());
        self::assertSame('boolean', $predicate->type->name);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testInputsRejectsSqlite(): void
    {
        $string = Expression::literal('a', Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new NormalizedPredicate($string->source, $string, UnicodeNormalForm::Nfc, false);
    }

    public function testSpellingWritesThePredicate(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        self::assertSame('IS NOT NFD NORMALIZED', (new NormalizedPredicate($string->source, $string, UnicodeNormalForm::Nfd, true))->spelling());
    }

    public function testWithFactsPreservesTheFormAndNegation(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $predicate = new NormalizedPredicate($string->source, $string, UnicodeNormalForm::Nfkd, true);
        $copy = $predicate->withFacts($predicate->facts);
        self::assertNotSame($predicate, $copy);
        self::assertSame(UnicodeNormalForm::Nfkd, $copy->form);
        self::assertTrue($copy->negated);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $predicate = new NormalizedPredicate($string->source, $string, UnicodeNormalForm::Nfc, false);
        $this->expectException(InvalidStructure::class);
        $predicate->withFacts($string->facts);
    }
}
