<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;

#[CoversClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalCatalogException::class)]
final class LexicalWitnessCheckTest extends TestCase
{
    public function testVerifyAcceptsWitnessesThatAgreeWithTheCoverage(): void
    {
        (new LexicalWitnessCheck())->verify(
            ['IDENT' => [['id' => 'ident.bare', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => ['identifier']]]],
            [],
            ['units' => ['identifier'], 'witnessed' => ['identifier' => 'ident.bare'], 'excluded' => []],
        );

        $this->expectNotToPerformAssertions();
    }

    public function testVerifyRejectsCoverageNamingAWitnessThatDoesNotExist(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('unknown witness: identifier');

        (new LexicalWitnessCheck())->verify(
            ['IDENT' => [['id' => 'ident.bare', 'sql' => 'name', 'tokens' => ['IDENT'], 'units' => ['identifier']]]],
            [],
            ['units' => ['identifier'], 'witnessed' => ['identifier' => 'missing'], 'excluded' => []],
        );
    }

    public function testIdentifiersOfCollectsEveryWitnessIdentifier(): void
    {
        $identifiers = (new LexicalWitnessCheck())->identifiersOf(
            [
                'IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => ['identifier']]],
                'STRING' => [['id' => 'b', 'sql' => 'y', 'tokens' => [], 'units' => ['identifier']]],
            ],
            [],
            ['identifier'],
        );

        self::assertSame(['a', 'b'], array_keys($identifiers));
    }

    public function testIdentifiersOfRejectsATerminalThatIsAlsoExcluded(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('catalog and exclusions must be disjoint');

        (new LexicalWitnessCheck())->identifiersOf(
            ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => []]]],
            ['IDENT' => 'reason'],
            [],
        );
    }

    public function testIdentifiersOfRejectsACataloguedTerminalWithNoWitnesses(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Terminal catalog is empty: IDENT');

        (new LexicalWitnessCheck())->identifiersOf(['IDENT' => []], [], []);
    }

    public function testIdentifiersOfRejectsARepeatedIdentifier(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Duplicate terminal witness identifier: a');

        (new LexicalWitnessCheck())->identifiersOf(
            [
                'IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => []]],
                'STRING' => [['id' => 'a', 'sql' => 'y', 'tokens' => [], 'units' => []]],
            ],
            [],
            [],
        );
    }

    public function testIdentifiersOfRejectsAWitnessClaimingAnUnlistedUnit(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('unknown coverage unit: unknown');

        (new LexicalWitnessCheck())->identifiersOf(
            ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => ['unknown']]]],
            [],
            ['identifier'],
        );
    }

    public function testVerifyCoverageNamesRealWitnessesRejectsAWitnessThatDoesNotClaimTheUnit(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('does not reference its unit: identifier');

        (new LexicalWitnessCheck())->verifyCoverageNamesRealWitnesses(
            ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => []]]],
            ['identifier' => 'a'],
            ['a' => true],
        );
    }

    public function testVerifyCoverageNamesRealWitnessesAcceptsAnEmptyCoverage(): void
    {
        (new LexicalWitnessCheck())->verifyCoverageNamesRealWitnesses([], [], []);

        $this->expectNotToPerformAssertions();
    }

    public function testCoversFindsTheWitnessThatClaimsTheUnit(): void
    {
        $terminals = ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => ['identifier']]]];

        self::assertTrue((new LexicalWitnessCheck())->covers($terminals, 'a', 'identifier'));
    }

    public function testCoversRejectsAWitnessThatClaimsAnotherUnit(): void
    {
        $terminals = ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => ['comment']]]];

        self::assertFalse((new LexicalWitnessCheck())->covers($terminals, 'a', 'identifier'));
    }

    public function testCoversRejectsAnIdentifierNoWitnessCarries(): void
    {
        $terminals = ['IDENT' => [['id' => 'a', 'sql' => 'x', 'tokens' => [], 'units' => ['identifier']]]];

        self::assertFalse((new LexicalWitnessCheck())->covers($terminals, 'b', 'identifier'));
    }
}
