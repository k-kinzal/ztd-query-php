<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;

#[CoversClass(LexicalCatalogException::class)]
final class LexicalCatalogExceptionTest extends TestCase
{
    public function testMalformedShapeNamesTheField(): void
    {
        self::assertSame(
            'Invalid upstream lexical catalog shape. Field: source.engine',
            LexicalCatalogException::malformedShape('source.engine')->getMessage(),
        );
    }

    public function testMalformedTerminalCatalogDescribesTheSection(): void
    {
        self::assertSame(
            'Invalid upstream lexical terminal catalog.',
            LexicalCatalogException::malformedTerminalCatalog()->getMessage(),
        );
    }

    public function testMalformedWitnessNamesTheTerminal(): void
    {
        self::assertSame(
            'Invalid terminal witness: IDENT',
            LexicalCatalogException::malformedWitness('IDENT')->getMessage(),
        );
    }

    public function testMalformedExclusionDescribesTheRequirement(): void
    {
        self::assertSame(
            'Terminal exclusions require string terminals and nonempty reasons.',
            LexicalCatalogException::malformedExclusion()->getMessage(),
        );
    }

    public function testMalformedCoverageUnitsDescribesTheRequirement(): void
    {
        self::assertSame(
            'Coverage units must be a list of strings.',
            LexicalCatalogException::malformedCoverageUnits()->getMessage(),
        );
    }

    public function testMalformedCoverageWitnessDescribesTheRequirement(): void
    {
        self::assertSame(
            'Coverage witnesses require string units and identifiers.',
            LexicalCatalogException::malformedCoverageWitness()->getMessage(),
        );
    }

    public function testMalformedCoverageExclusionDescribesTheRequirement(): void
    {
        self::assertSame(
            'Coverage exclusions require string units and nonempty reasons.',
            LexicalCatalogException::malformedCoverageExclusion()->getMessage(),
        );
    }

    public function testDuplicateCoverageUnitsDescribesTheDuplication(): void
    {
        self::assertSame(
            'Upstream lexer coverage units must be unique.',
            LexicalCatalogException::duplicateCoverageUnits()->getMessage(),
        );
    }

    public function testOverlappingClassificationDescribesTheOverlap(): void
    {
        self::assertSame(
            'Upstream lexer coverage units cannot be both witnessed and excluded.',
            LexicalCatalogException::overlappingClassification()->getMessage(),
        );
    }

    public function testIncompleteClassificationDescribesTheGap(): void
    {
        self::assertSame(
            'Upstream lexer coverage units are not completely classified.',
            LexicalCatalogException::incompleteClassification()->getMessage(),
        );
    }

    public function testTerminalIsAlsoExcludedDescribesTheContradiction(): void
    {
        self::assertSame(
            'Terminal catalog and exclusions must be disjoint string keys.',
            LexicalCatalogException::terminalIsAlsoExcluded()->getMessage(),
        );
    }

    public function testEmptyTerminalNamesTheTerminal(): void
    {
        self::assertSame(
            'Terminal catalog is empty: IDENT',
            LexicalCatalogException::emptyTerminal('IDENT')->getMessage(),
        );
    }

    public function testDuplicateWitnessIdNamesTheIdentifier(): void
    {
        self::assertSame(
            'Duplicate terminal witness identifier: ident.bare',
            LexicalCatalogException::duplicateWitnessId('ident.bare')->getMessage(),
        );
    }

    public function testUnknownCoverageUnitNamesTheUnit(): void
    {
        self::assertSame(
            'Terminal witness references an unknown coverage unit: unknown',
            LexicalCatalogException::unknownCoverageUnit('unknown')->getMessage(),
        );
    }

    public function testUnknownWitnessNamesTheUnit(): void
    {
        self::assertSame(
            'Coverage unit references an unknown witness: identifier',
            LexicalCatalogException::unknownWitness('identifier')->getMessage(),
        );
    }

    public function testWitnessDoesNotCoverItsUnitNamesTheUnit(): void
    {
        self::assertSame(
            'Coverage witness does not reference its unit: identifier',
            LexicalCatalogException::witnessDoesNotCoverItsUnit('identifier')->getMessage(),
        );
    }

    public function testMissingTerminalsListsThemInOneMessage(): void
    {
        self::assertSame(
            'Upstream lexer catalog is missing grammar terminals: FROM_SYM, IDENT',
            LexicalCatalogException::missingTerminals(['FROM_SYM', 'IDENT'])->getMessage(),
        );
    }
}
