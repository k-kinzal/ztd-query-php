<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;

#[CoversClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalCatalogException::class)]
final class LexicalCoverageCheckTest extends TestCase
{
    public function testVerifyAcceptsAPartitionedCoverage(): void
    {
        (new LexicalCoverageCheck())->verify([
            'units' => ['identifier', 'comment'],
            'witnessed' => ['identifier' => 'ident.bare'],
            'excluded' => ['comment' => 'not generated'],
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testVerifyRejectsAUnitThatIsNeitherWitnessedNorExcluded(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('not completely classified');

        (new LexicalCoverageCheck())->verify([
            'units' => ['identifier', 'comment'],
            'witnessed' => ['identifier' => 'ident.bare'],
            'excluded' => [],
        ]);
    }

    public function testVerifyUnitsAreUniqueAcceptsDistinctUnits(): void
    {
        (new LexicalCoverageCheck())->verifyUnitsAreUnique(['identifier', 'comment']);

        $this->expectNotToPerformAssertions();
    }

    public function testVerifyUnitsAreUniqueRejectsARepeatedUnit(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('coverage units must be unique');

        (new LexicalCoverageCheck())->verifyUnitsAreUnique(['identifier', 'identifier']);
    }

    public function testVerifyClassificationsDoNotOverlapAcceptsDisjointMaps(): void
    {
        (new LexicalCoverageCheck())
            ->verifyClassificationsDoNotOverlap(['identifier' => 'id'], ['comment' => 'reason']);

        $this->expectNotToPerformAssertions();
    }

    public function testVerifyClassificationsDoNotOverlapRejectsASharedUnit(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('both witnessed and excluded');

        (new LexicalCoverageCheck())
            ->verifyClassificationsDoNotOverlap(['identifier' => 'id'], ['identifier' => 'reason']);
    }

    public function testVerifyEveryUnitIsClassifiedIgnoresTheOrderUnitsAreWrittenIn(): void
    {
        (new LexicalCoverageCheck())->verifyEveryUnitIsClassified([
            'units' => ['comment', 'identifier'],
            'witnessed' => ['identifier' => 'ident.bare'],
            'excluded' => ['comment' => 'not generated'],
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function testVerifyEveryUnitIsClassifiedRejectsAClassifiedUnitThatIsNotListed(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('not completely classified');

        (new LexicalCoverageCheck())->verifyEveryUnitIsClassified([
            'units' => ['identifier'],
            'witnessed' => ['identifier' => 'ident.bare'],
            'excluded' => ['unlisted' => 'reason'],
        ]);
    }
}
