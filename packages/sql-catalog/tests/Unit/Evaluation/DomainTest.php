<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Domain::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class DomainTest extends TestCase
{
    public function testOfHoldsOneAlternative(): void
    {
        self::assertCount(1, Domain::of(new LiteralTerm('a'))->terms);
    }

    public function testLiteralHoldsOneResolvedScalar(): void
    {
        self::assertSame('a', Domain::literal('a')->soleLiteral()?->value);
    }

    public function testOpaqueCarriesTypeAndOrigin(): void
    {
        $domain = Domain::opaque(TypeShape::of(['int']), Origin::Parameter, '$id');
        self::assertSame('int', $domain->type()->display());
        self::assertFalse($domain->isExact());
    }

    public function testUnknownKnowsNothing(): void
    {
        self::assertSame('mixed', Domain::unknown()->type()->display());
    }

    public function testFromTermsDeduplicatesAlternatives(): void
    {
        $domain = Domain::fromTerms([new LiteralTerm('a'), new LiteralTerm('a'), new LiteralTerm('b')]);
        self::assertCount(2, $domain->terms);
    }

    public function testFromTermsFallsBackToUnknownWhenGivenNothing(): void
    {
        self::assertSame('mixed', Domain::fromTerms([])->type()->display());
    }

    public function testFromTermsGeneralizesBeyondTheBound(): void
    {
        $terms = array_map(
            static fn (int $index): LiteralTerm => new LiteralTerm('value' . $index),
            range(0, Domain::MAX_TERMS),
        );
        $domain = Domain::fromTerms($terms);
        self::assertCount(1, $domain->terms);
        self::assertTrue($domain->widened);
    }

    public function testGeneralizeCoversEveryAlternative(): void
    {
        $term = Domain::generalize([new LiteralTerm('ORDER BY a'), new LiteralTerm('ORDER BY b')], Origin::Branch);
        self::assertSame('ORDER BY {$}', $term->toPattern()->display());
    }

    public function testUnionKeepsBothSetsOfAlternatives(): void
    {
        $domain = Domain::literal('a')->union(Domain::literal('b'));
        self::assertCount(2, $domain->terms);
    }

    public function testConcatBuildsEveryCombination(): void
    {
        $domain = Domain::literal('a')->union(Domain::literal('b'))->concat(Domain::literal('!'));
        self::assertCount(2, $domain->terms);
        self::assertSame(['a!', 'b!'], array_map(
            static fn (TextPattern $pattern): ?string => $pattern->text(),
            $domain->patterns(),
        ));
    }

    public function testConcatKeepsAGapFromEitherSide(): void
    {
        $domain = Domain::literal('id = ')->concat(Domain::opaque(TypeShape::unknown(), Origin::External));
        self::assertSame('id = {$}', $domain->patterns()[0]->display());
    }

    public function testAsTermCollapsesAResolvedPatternToALiteral(): void
    {
        self::assertInstanceOf(LiteralTerm::class, Domain::asTerm(TextPattern::fromText('a')));
    }

    public function testAsTermKeepsAPatternThatStillHasGaps(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Loop, TypeShape::unknown()));
        self::assertInstanceOf(PatternTerm::class, Domain::asTerm($pattern));
    }

    public function testCollapseFoldsAlternativesIntoOneShape(): void
    {
        $domain = Domain::literal('a')->union(Domain::literal('b'))->collapse(Origin::Loop);
        self::assertCount(1, $domain->terms);
        self::assertTrue($domain->widened);
    }

    public function testCollapseLeavesASingleAlternativeAlone(): void
    {
        $domain = Domain::literal('a')->collapse(Origin::Loop);
        self::assertFalse($domain->widened);
    }

    public function testSoleLiteralOnlyAnswersForOneResolvedScalar(): void
    {
        self::assertNull(Domain::literal('a')->union(Domain::literal('b'))->soleLiteral());
        self::assertNull(Domain::unknown()->soleLiteral());
    }

    public function testSoleArrayOnlyAnswersForOneArray(): void
    {
        self::assertNotNull(Domain::of(new ArrayTerm([new ArrayEntry(null, Domain::literal(1))]))->soleArray());
        self::assertNull(Domain::literal('a')->soleArray());
    }

    public function testSoleObjectOnlyAnswersForOneObject(): void
    {
        self::assertSame('PDO', Domain::of(new ObjectTerm('PDO'))->soleObject()?->className);
        self::assertNull(Domain::literal('a')->soleObject());
    }

    public function testPatternsWritesEveryAlternative(): void
    {
        self::assertCount(2, Domain::literal('a')->union(Domain::literal('b'))->patterns());
    }

    public function testIsExactOnlyWhenEveryAlternativeResolved(): void
    {
        self::assertTrue(Domain::literal('a')->union(Domain::literal('b'))->isExact());
        self::assertFalse(Domain::literal('a')->union(Domain::unknown())->isExact());
    }

    public function testTypeCoversEveryAlternative(): void
    {
        self::assertSame('int|string', Domain::literal('a')->union(Domain::literal(1))->type()->display());
    }

    public function testSignatureIgnoresTheOrderOfAlternatives(): void
    {
        $left = Domain::literal('a')->union(Domain::literal('b'));
        $right = Domain::literal('b')->union(Domain::literal('a'));
        self::assertSame($left->signature(), $right->signature());
    }

    public function testEqualsComparesTheSetOfAlternatives(): void
    {
        self::assertTrue(Domain::literal('a')->equals(Domain::literal('a')));
        self::assertFalse(Domain::literal('a')->equals(Domain::literal('b')));
    }
    public function testWithVariableNamesOnlyOpaqueAlternativesWithoutChangingTheirMeaning(): void
    {
        $value = Domain::fromTerms([
            new OpaqueTerm(TypeShape::of(['string']), Origin::Call, 'buildSql()'),
            new LiteralTerm('SELECT 1'),
        ], true, true);
        $named = $value->withVariable('$sql');
        $hole = $named->patterns()[0]->holes()[0];

        self::assertSame('$sql', $hole->variable);
        self::assertSame('buildSql()', $hole->expression);
        self::assertSame(Origin::Call, $hole->origin);
        self::assertSame('string', $hole->type->display());
        self::assertSame('SELECT 1', $named->patterns()[1]->text());
        self::assertTrue($named->widened);
        self::assertTrue($named->combined);
        self::assertSame($value->signature(), $named->signature());
        self::assertNull($value->patterns()[0]->holes()[0]->variable);
    }

    public function testWithVariablePreservesResolvedDomainsAndExistingFragmentNames(): void
    {
        $resolved = Domain::literal('SELECT 1');
        $fragment = Domain::of(new PatternTerm(TextPattern::fromText('SELECT * FROM ')->concat(
            TextPattern::fromHole(new TextHole(Origin::Parameter, TypeShape::of(['string']), '$table', '$table')),
        )));

        self::assertSame($resolved, $resolved->withVariable('$sql'));
        self::assertSame($fragment, $fragment->withVariable('$sql'));
    }

}
