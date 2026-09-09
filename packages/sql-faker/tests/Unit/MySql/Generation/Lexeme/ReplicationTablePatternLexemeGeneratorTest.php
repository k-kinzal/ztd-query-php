<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator;

#[CoversClass(ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ReplicationTablePatternLexemeGeneratorTest extends TestCase
{
    public function testGenerateKeepsDefaultPatternsAndOrdinaryStringsSeparate(): void
    {
        $generator = new ReplicationTablePatternLexemeGenerator();
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['REPLICATION_TABLE_PATTERN']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(["'db.table'", "'db.%'", "'%.table'"], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['TEXT_STRING']), 0, new ResolvedOutput())));
    }

    #[DataProvider('providerPatterns')]
    public function testGenerateValidatesDecodedPatternsWithoutChangingTheirSpelling(string $spelling, bool $valid): void
    {
        $generator = new ReplicationTablePatternLexemeGenerator();
        $input = new LexemeInput(TerminalSequence::fromNames(['REPLICATION_TABLE_PATTERN']), 0, new ResolvedOutput(), $spelling);
        $result = $generator->generate($input);
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertSame($valid ? [$spelling] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, $candidates));
    }

    #[DataProvider('providerPatterns')]
    public function testAcceptsValidatesTheDecodedParserValue(string $spelling, bool $valid): void
    {
        self::assertSame($valid, (new ReplicationTablePatternLexemeGenerator())->accepts($spelling));
    }

    public function testGeneratePreservesExplicitCandidateProvenance(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['REPLICATION_TABLE_PATTERN']), 0, new ResolvedOutput(), "'db.%'");
        $result = (new ReplicationTablePatternLexemeGenerator())->generate($input);
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertCount(1, $candidates);
        self::assertSame($input->terminal(), $candidates[0]->lexemes[0]->origin);
        self::assertSame('sql/sql_yacc.yy:filter_wild_db_table_string', $candidates[0]->lexemes[0]->definition);
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerPatterns(): iterable
    {
        yield ["'db.table'", true];
        yield ["'.'", true];
        yield ["'%.%'", true];
        yield ["'db.it''s'", true];
        yield ["'db.\\_%'", true];
        yield ["'db\\.table'", true];
        yield ["'db.\\\\n'", true];
        yield ["'db.\\0\\n'", true];
        yield ["'db.\\n'", false];
        yield ["'db.\n'", false];
        yield ["'db.\\\n'", false];
        yield ["'db\\0.table'", false];
        yield ["'db.table\0'", false];
        yield ["'text'", false];
        yield ["''", false];
        yield ['db.table', false];
        yield ["'db.table", false];
        yield ["'db.table' trailing", false];
        yield ["'db.t'able'", false];
    }
}
