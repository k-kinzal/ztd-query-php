<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker\Fuzz;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Fuzz\Target\SyntaxFailure;

#[CoversClass(PgSyntaxCheck::class)]
final class PgSyntaxCheckTest extends TestCase
{
    #[DataProvider('providerGrammarErrors')]
    public function testRejectionKeepsGrammarAndScannerErrorsAsFindings(string $state, string $message, string $source): void
    {
        $this->expectException(SyntaxFailure::class);
        PgSyntaxCheck::rejection($state, 'ERROR:  ' . $message, 'generated SQL', '00', $source);
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function providerGrammarErrors(): iterable
    {
        yield ['0A000', 'only string constants are supported in JSON_TABLE path specification', 'gram.y'];
        yield ['0A000', 'CREATE SCHEMA IF NOT EXISTS cannot include schema elements', 'gram.y'];
        yield ['0A000', 'CREATE OR REPLACE CONSTRAINT TRIGGER is not supported', 'gram.y'];
        yield ['0A000', 'CHECK constraints cannot be marked DEFERRABLE', 'gram.y'];
        yield ['0A000', 'WITH CHECK OPTION not supported on recursive views', 'gram.y'];
        yield ['0A000', 'aggregates cannot have output arguments', 'gram.y'];
        yield ['0A000', 'unknown feature rejection', 'unknown.c'];
        yield ['0A000', 'unknown feature rejection', ''];
        yield ['42601', 'syntax error', 'gram.y'];
        yield ['22023', 'bad grammar action argument', 'gram.y'];
        yield ['22P02', 'bad scanner value', 'scan.l'];
    }

    public function testRejectionSeparatesExplicitUnsupportedSyntaxFromAcceptance(): void
    {
        $result = PgSyntaxCheck::rejection('0A000', 'ERROR:  CREATE ASSERTION is not yet implemented', 'SQL', '00', 'gram.y');
        self::assertSame('unsupported', $result->status);
        self::assertSame('0A000', $result->code);
        self::assertSame('gram.y', $result->source);
    }

    public function testRejectionKeepsSemanticAnalysisInconclusive(): void
    {
        $result = PgSyntaxCheck::rejection('42P01', 'ERROR:  missing relation', 'SQL', '00', 'parse_relation.c');
        self::assertSame('semantic-inconclusive', $result->status);
        self::assertSame('42P01', $result->code);
        self::assertSame('semantic-inconclusive', PgSyntaxCheck::rejection('0A000', 'ERROR:  unsupported analyzed expression', 'SQL', '00', 'parse_expr.c')->status);
    }
}
