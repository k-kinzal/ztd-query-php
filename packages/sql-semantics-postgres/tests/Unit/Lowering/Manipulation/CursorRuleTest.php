<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\CursorRule::class)]
#[Medium]
final class CursorRuleTest extends TestCase
{
    public function testDeclareLowersTheHoldability(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DECLARE c CURSOR WITHOUT HOLD FOR SELECT 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\DeclareCursor::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Holdability::Without, $statement->hold);
    }

    public function testOptionsKeepsTheWrittenOrder(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DECLARE c SCROLL BINARY INSENSITIVE CURSOR FOR SELECT 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\DeclareCursor::class, $statement);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\CursorOption::Scroll, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\CursorOption::Binary, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\CursorOption::Insensitive], $statement->options);
    }

    public function testFetchLowersTheMovementAndTheCount(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MOVE BACKWARD -2 IN c');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Fetch::class, $statement);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\FetchMovement::BackwardCount, true, true], [$statement->movement, $statement->move, $statement->count?->negative]);
    }

    public function testFromAcceptsTheNoiseWords(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('FETCH ALL IN c');
        self::assertSame('FETCH ALL c', $query->toString());
    }

    public function testCloseLowersTheCursor(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('CLOSE c');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Close::class, $statement);
        self::assertSame('c', $statement->cursor?->value);
    }
}
