<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\MySqlLexicalCoverage;

#[CoversClass(MySqlLexicalCoverage::class)]
final class MySqlLexicalCoverageTest extends TestCase
{
    public function testFillers(): void
    {
        self::assertSame(
            ['mysql.coverage.MY_LEX_END', 'mysql.coverage.MY_LEX_EOL', 'mysql.coverage.MY_LEX_OPERATOR_OR_IDENT'],
            array_column(
                (new MySqlLexicalCoverage())->fillers(['MY_LEX_END', 'MY_LEX_EOL', 'MY_LEX_OPERATOR_OR_IDENT']),
                'id',
            ),
        );
    }

    public function testFillersLeavesOutAStateTheLexerDoesNotDeclare(): void
    {
        self::assertSame([], (new MySqlLexicalCoverage())->fillers(['MY_LEX_START']));
    }

    public function testFillersReadsTheOperatorStateFromTextThatEntersIt(): void
    {
        self::assertSame(
            [['id' => 'mysql.coverage.MY_LEX_OPERATOR_OR_IDENT', 'sql' => 'a + b', 'tokens' => ['IDENT', '+', 'IDENT'], 'units' => ['MY_LEX_OPERATOR_OR_IDENT']]],
            (new MySqlLexicalCoverage())->fillers(['MY_LEX_OPERATOR_OR_IDENT']),
        );
    }

    public function testWitnessed(): void
    {
        self::assertSame(
            ['MY_LEX_START' => 'first', 'MY_LEX_IDENT' => 'second'],
            (new MySqlLexicalCoverage())->witnessed(
                [
                    'IDENT' => [
                        ['id' => 'first', 'units' => ['MY_LEX_START']],
                        ['id' => 'second', 'units' => ['MY_LEX_START', 'MY_LEX_IDENT']],
                    ],
                ],
                ['MY_LEX_START', 'MY_LEX_IDENT'],
            ),
        );
    }

    public function testWitnessedReportsAStateNothingReaches(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('misses lexical states: MY_LEX_EOL');

        (new MySqlLexicalCoverage())->witnessed(
            ['IDENT' => [['id' => 'first', 'units' => ['MY_LEX_START']]]],
            ['MY_LEX_START', 'MY_LEX_EOL'],
        );
    }
}
