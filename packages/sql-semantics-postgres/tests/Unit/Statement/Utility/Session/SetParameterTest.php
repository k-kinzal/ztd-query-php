<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameter::class)]
#[Medium]
final class SetParameterTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET search_path TO a, \'b\', 1, on')->facts->diagnostics);
    }

    public function testDeriveStatementReturnsNoRows(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a = 1');
        self::assertSame(null, $operation->facts->output);
    }

    public function testRenderWritesToForEitherWord(): void
    {
        self::assertSame('SET a TO 1, \'x\', - 2.5, TRUE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a = 1, \'x\', -2.5, true')->toString());
    }

    public function testRenderDropsSessionAndKeepsLocal(): void
    {
        self::assertSame(['SET a TO b', 'SET LOCAL a TO b'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION a TO b')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET LOCAL a TO b')->toString()]);
    }

    public function testValuesAreKeptInOrder(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET a TO x, y')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameter::class, $statement);
        self::assertCount(2, $statement->values);
    }
}
