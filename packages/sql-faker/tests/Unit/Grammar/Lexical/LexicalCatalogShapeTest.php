<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;

#[CoversClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(LexicalWitnessShape::class)]
final class LexicalCatalogShapeTest extends TestCase
{
    public function testOfReadsEverySectionOfAWellFormedCatalog(): void
    {
        $read = (new LexicalCatalogShape())->of([
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => ['IDENT' => [['ident.bare', 'name', ['IDENT'], ['identifier']]]],
            'terminal_exclusions' => ['MODE' => 'not generated'],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'ident.bare'],
                'excluded' => [],
            ],
        ]);

        self::assertSame('official', $read['source']['engine']);
        self::assertSame('ident.bare', $read['terminals']['IDENT'][0]['id']);
        self::assertSame(['MODE' => 'not generated'], $read['terminal_exclusions']);
        self::assertSame(['identifier'], $read['coverage']['units']);
    }

    public function testSourceOfReadsTheUpstreamLexersIdentity(): void
    {
        $source = (new LexicalCatalogShape())
            ->sourceOf(['source' => ['engine' => 'official', 'entrypoint' => 'lexer']]);

        self::assertSame(['engine' => 'official', 'entrypoint' => 'lexer'], $source);
    }

    public function testSourceOfNamesTheMissingField(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Field: source.entrypoint');

        (new LexicalCatalogShape())->sourceOf(['source' => ['engine' => 'official']]);
    }

    public function testTerminalsOfExpandsEveryWitnessItReads(): void
    {
        $terminals = (new LexicalCatalogShape())
            ->terminalsOf(['terminals' => ['IDENT' => [['ident.bare', 'name', ['IDENT'], ['identifier']]]]]);

        self::assertSame('name', $terminals['IDENT'][0]['sql']);
    }

    public function testTerminalsOfRejectsANonStringTerminalName(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Invalid upstream lexical terminal catalog.');

        (new LexicalCatalogShape())->terminalsOf(['terminals' => [0 => []]]);
    }

    public function testExclusionsOfReadsTheReasons(): void
    {
        $exclusions = (new LexicalCatalogShape())
            ->exclusionsOf(['terminal_exclusions' => ['MODE' => 'not generated']]);

        self::assertSame(['MODE' => 'not generated'], $exclusions);
    }

    public function testExclusionsOfRejectsAnEmptyReason(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('nonempty reasons');

        (new LexicalCatalogShape())->exclusionsOf(['terminal_exclusions' => ['MODE' => '']]);
    }

    public function testCoverageOfReadsAllThreeParts(): void
    {
        $coverage = (new LexicalCatalogShape())->coverageOf([
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'ident.bare'],
                'excluded' => [],
            ],
        ]);

        self::assertSame(['identifier'], $coverage['units']);
        self::assertSame(['identifier' => 'ident.bare'], $coverage['witnessed']);
        self::assertSame([], $coverage['excluded']);
    }

    public function testCoverageOfNamesTheMissingSection(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Field: coverage');

        (new LexicalCatalogShape())->coverageOf([]);
    }

    public function testUnitsOfReadsTheListOfNames(): void
    {
        self::assertSame(
            ['identifier', 'comment'],
            (new LexicalCatalogShape())->unitsOf(['units' => ['identifier', 'comment']]),
        );
    }

    public function testUnitsOfRejectsAKeyedArray(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('Coverage units must be a list of strings.');

        (new LexicalCatalogShape())->unitsOf(['units' => [1 => 'identifier']]);
    }

    public function testWitnessedOfReadsTheIdentifierPerUnit(): void
    {
        self::assertSame(
            ['identifier' => 'ident.bare'],
            (new LexicalCatalogShape())->witnessedOf(['witnessed' => ['identifier' => 'ident.bare']]),
        );
    }

    public function testWitnessedOfRejectsANonStringIdentifier(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('string units and identifiers');

        (new LexicalCatalogShape())->witnessedOf(['witnessed' => ['identifier' => 1]]);
    }

    public function testExcludedOfReadsTheReasonPerUnit(): void
    {
        self::assertSame(
            ['comment' => 'not generated'],
            (new LexicalCatalogShape())->excludedOf(['excluded' => ['comment' => 'not generated']]),
        );
    }

    public function testExcludedOfRejectsAnEmptyReason(): void
    {
        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('string units and nonempty reasons');

        (new LexicalCatalogShape())->excludedOf(['excluded' => ['comment' => '']]);
    }
}
