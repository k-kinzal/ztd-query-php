<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\Lexical\LexicalSourceParser;

#[CoversClass(LexicalSourceParser::class)]
final class LexicalSourceParserTest extends TestCase
{
    public function testParsesEveryDeclaredState(): void
    {
        $states = (new LexicalSourceParser())->parseStates(
            "enum my_lex_states {\n MY_LEX_START, MY_LEX_CHAR, MY_LEX_START, MY_LEX_ESCAPE\n};",
            'case MY_LEX_START: case MY_LEX_CHAR:',
        );

        self::assertSame(['MY_LEX_START', 'MY_LEX_CHAR', 'MY_LEX_ESCAPE'], $states);
    }

    public function testRejectsADeclaredStateMissingFromTheScanner(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL scanner does not classify lexical state: MY_LEX_NEW_STATE');

        (new LexicalSourceParser())->parseStates(
            'enum my_lex_states { MY_LEX_START, MY_LEX_NEW_STATE };',
            'case MY_LEX_START: label MY_LEX_NEW_STATE: case MY_LEX_NEW_STATE',
        );
    }

    public function testRejectsMissingStateEnum(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL lexical state enum was not found.');

        (new LexicalSourceParser())->parseStates('', '');
    }

    public function testRejectsEmptyStateEnum(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL lexical state inventory was empty.');

        (new LexicalSourceParser())->parseStates('enum my_lex_states {};', '');
    }

    public function testParseStatesNamesEveryStateTheScannerClassifies(): void
    {
        $states = (new LexicalSourceParser())->parseStates(
            'enum my_lex_states { MY_LEX_START, MY_LEX_CHAR };',
            'case MY_LEX_START: case MY_LEX_CHAR:',
        );

        self::assertSame(['MY_LEX_START', 'MY_LEX_CHAR'], $states);
    }

    public function testParseStatesReportsASourceThatDeclaresNoStateEnum(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL lexical state enum was not found.');

        (new LexicalSourceParser())->parseStates('', '');
    }

    public function testParseStatesReportsAStateTheScannerLeavesUnclassified(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL scanner does not classify lexical state: MY_LEX_START');

        (new LexicalSourceParser())->parseStates('enum my_lex_states { MY_LEX_START };', '');
    }
}
