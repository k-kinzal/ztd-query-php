<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ValueDomain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Text\LiteralText::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
final class ValueDomainTest extends TestCase
{
    public function testFromDomainListsTheResolvedAlternatives(): void
    {
        $value = ValueDomain::fromDomain(Domain::literal('active')->union(Domain::literal('banned')));
        self::assertSame(['active', 'banned'], $value->values);
        self::assertTrue($value->exhaustive);
        self::assertSame('string', $value->type);
    }

    public function testFromDomainReportsOnlyTheTypeWhenAValueDidNotResolve(): void
    {
        $value = ValueDomain::fromDomain(Domain::opaque(TypeShape::of(['int', 'null']), Origin::Parameter));
        self::assertSame([], $value->values);
        self::assertFalse($value->exhaustive);
        self::assertSame(['parameter'], $value->origins);
        self::assertSame('int|null', $value->type);
    }

    public function testFromDomainIsNotExhaustiveOnceTheAlternativesWereWidened(): void
    {
        $widened = Domain::literal('a')->union(Domain::literal('b'))->collapse(Origin::Branch);
        self::assertFalse(ValueDomain::fromDomain($widened)->exhaustive);
    }

    public function testFromDomainRecordsAnUnresolvedOriginForANonScalarTerm(): void
    {
        self::assertSame(['unresolved'], ValueDomain::fromDomain(Domain::of(new ArrayTerm([])))->origins);
    }

    public function testIsResolvedNeedsBothExhaustivenessAndValues(): void
    {
        self::assertTrue(ValueDomain::fromDomain(Domain::literal('a'))->isResolved());
        self::assertFalse(ValueDomain::fromDomain(Domain::unknown())->isResolved());
    }

    public function testAdmitsEverythingWhenNothingWasResolved(): void
    {
        self::assertTrue(ValueDomain::fromDomain(Domain::unknown())->admits('anything'));
    }

    public function testAdmitsOnlyTheResolvedAlternatives(): void
    {
        $value = ValueDomain::fromDomain(Domain::literal('active')->union(Domain::literal('banned')));
        self::assertTrue($value->admits('active'));
        self::assertFalse($value->admits('deleted'));
    }

    public function testAdmitsAcrossTheDriverWideningIntegersToStrings(): void
    {
        self::assertTrue(ValueDomain::fromDomain(Domain::literal(1))->admits('1'));
    }

    public function testDisplayWritesTheAlternativesOrTheType(): void
    {
        self::assertSame("'a'|1", ValueDomain::fromDomain(Domain::literal('a')->union(Domain::literal(1)))->display());
        self::assertSame('mixed', ValueDomain::fromDomain(Domain::unknown())->display());
    }
}
