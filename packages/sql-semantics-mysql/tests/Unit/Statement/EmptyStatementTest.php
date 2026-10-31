<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\EmptyStatement;

#[CoversClass(EmptyStatement::class)]
#[Medium]
final class EmptyStatementTest extends TestCase
{
    public function testRenderEmitsNoTokens(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('');
        self::assertInstanceOf(EmptyStatement::class, $statement);
        self::assertSame('', $statement->toString());
    }

    public function testDeriveStatementHasNoResultOrDeclaration(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('');
        self::assertInstanceOf(EmptyStatement::class, $statement);
        self::assertNull($statement->output());
        self::assertSame([], $statement->declarations());
        self::assertSame([], $statement->diagnostics());
    }
}
