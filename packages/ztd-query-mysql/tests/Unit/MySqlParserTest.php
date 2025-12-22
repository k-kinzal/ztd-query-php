<?php

declare(strict_types=1);

namespace Tests\Unit;

use ZtdQuery\Platform\MySql\MySqlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySqlParser::class)]
final class MySqlParserTest extends TestCase
{
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
}
