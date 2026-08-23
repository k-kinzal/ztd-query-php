<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlParser;

#[CoversClass(MySqlParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlParserTest extends TestCase
{
    public function testResolvesCompoundSelectParserArtifactsAsOneLogicalStatement(): void
    {
        $parser = new MySqlParser();

        self::assertInstanceOf(
            SelectStatement::class,
            $parser->parseSingleLogicalStatement('SELECT id FROM archive UNION ALL SELECT id FROM current'),
        );
        self::assertInstanceOf(
            InsertStatement::class,
            $parser->parseSingleLogicalStatement('INSERT INTO combined (id) SELECT id FROM archive UNION ALL SELECT id FROM current'),
        );
    }

    public function testRejectsMultipleAndNonSelectParserContinuations(): void
    {
        $parser = new MySqlParser();

        self::assertNull($parser->parseSingleLogicalStatement('SELECT 1; SELECT 2'));
        self::assertNull($parser->parseSingleLogicalStatement('UPDATE users SET active = 1 UNION SELECT 1'));
        self::assertNull($parser->parseSingleLogicalStatement('INSERT INTO users SELECT 1 UNION UPDATE users SET active = 1'));
        self::assertNull($parser->parseSingleLogicalStatement("INSERT INTO users SELECT 1 UNION SELECT 2 INTO OUTFILE '/tmp/result'"));
        self::assertNull($parser->parseSingleLogicalStatement(''));
    }

    public function testParseAndBuildRoundTrip(): void
    {
        $parser = new MySqlParser();
        $statements = $parser->parse('SELECT 1');

        self::assertCount(1, $statements);
        $sql = $statements[0]->build();

        self::assertStringContainsString('SELECT 1', $sql);
    }

    public function testParseReturnsListIndexedArray(): void
    {
        $parser = new MySqlParser();
        $statements = $parser->parse('SELECT 1; SELECT 2');

        self::assertCount(2, $statements);
        self::assertArrayHasKey(0, $statements);
        self::assertArrayHasKey(1, $statements);
    }

    public function testErrorHandlerIsRestoredAfterParse(): void
    {
        $parser = new MySqlParser();

        $handlerBefore = set_error_handler(static fn () => false);
        restore_error_handler();

        $parser->parse('SELECT 1');

        $handlerAfter = set_error_handler(static fn () => false);
        restore_error_handler();

        self::assertSame($handlerBefore, $handlerAfter);
    }

    public function testParseWithLargeIntLiteralDoesNotWarn(): void
    {
        $parser = new MySqlParser();
        $statements = $parser->parse('SELECT 99999999999999999999999');

        self::assertCount(1, $statements);
    }

    public function testNormalizesOptionalIntoAfterModifiersAndComments(): void
    {
        $statements = (new MySqlParser())->parse(
            "INSERT # lead\n HIGH_PRIORITY -- priority\n IGNORE users VALUE(/* value */ DEFAULT)",
        );
        $statement = $statements[0] ?? null;

        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertNotNull($statement->into);
        self::assertInstanceOf(Expression::class, $statement->into->dest);
        self::assertSame('users', $statement->into->dest->table);
        self::assertSame(['DEFAULT'], $statement->values[0]->values ?? null);
    }

    public function testLeavesExplicitIntoAndNonInsertStatementsUnchanged(): void
    {
        $parser = new MySqlParser();
        $explicit = $parser->parse('INSERT HIGH_PRIORITY INTO users VALUE(1)')[0] ?? null;
        $replace = $parser->parse('REPLACE users VALUE(1)')[0] ?? null;
        $expectedReplace = (new Parser('REPLACE users VALUE(1)'))->statements[0] ?? null;

        self::assertInstanceOf(InsertStatement::class, $explicit);
        self::assertNotNull($explicit->into);
        self::assertInstanceOf(Expression::class, $explicit->into->dest);
        self::assertSame('users', $explicit->into->dest->table);
        self::assertSame($expectedReplace?->build(), $replace?->build());
    }

    public function testLeavesEmptyAndIncompleteInsertStatementsForParserValidation(): void
    {
        $parser = new MySqlParser();

        self::assertSame([], $parser->parse(''));
        self::assertSame('INSERT INTO ', $parser->parse('INSERT')[0]->build());
    }

    public function testNormalizesOptionalIntoWithoutModifiers(): void
    {
        $statement = (new MySqlParser())->parse('INSERT users VALUE(1)')[0] ?? null;

        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertNotNull($statement->into);
        self::assertInstanceOf(Expression::class, $statement->into->dest);
        self::assertSame('users', $statement->into->dest->table);
    }

    public function testSplitsActualStatementsWithoutSplittingSetExpressions(): void
    {
        $parser = new MySqlParser();

        self::assertSame(['SELECT 1 EXCEPT SELECT 2'], $parser->splitStatements('SELECT 1 EXCEPT SELECT 2'));
        self::assertSame(['SELECT 1 INTERSECT SELECT 2'], $parser->splitStatements('SELECT 1 INTERSECT SELECT 2'));
        self::assertSame(['SELECT 1', 'SELECT 2'], $parser->splitStatements('SELECT 1; SELECT 2'));
    }
}
