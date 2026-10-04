<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Prepared\Execute::class)]
#[Medium]
final class ExecuteTest extends TestCase
{
    public function testDeriveStatementRecordsRowsThatDependOnTheSession(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('EXECUTE p (1)');
        self::assertSame('the session state: the prepared statement p', $query->facts->output?->shape->missing[0]->describe());
    }

    public function testDeriveParametersReportsDefault(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('EXECUTE p (DEFAULT)');
        self::assertSame(['DEFAULT is not allowed in this context'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics));
    }

    public function testRowsIsAnOpenShape(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('EXECUTE p');
        self::assertFalse($query->facts->output?->shape->complete());
    }

    public function testRenderWritesTheParameters(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze("EXECUTE p (1, 'x')");
        self::assertSame("EXECUTE p (1, 'x')", $query->toString());
    }
}
