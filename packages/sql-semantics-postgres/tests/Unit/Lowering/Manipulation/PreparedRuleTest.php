<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\PreparedRule::class)]
#[Medium]
final class PreparedRuleTest extends TestCase
{
    public function testPrepareLowersTheTypes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('PREPARE p (int, text[]) AS SELECT 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Prepare::class, $statement);
        self::assertCount(2, $statement->parameters?->types ?? []);
    }

    public function testExecuteLowersTheName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('EXECUTE p');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Execute::class, $statement);
        self::assertSame(['p', []], [$statement->name->value, $statement->parameters]);
    }

    public function testParametersLowersTheValues(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('EXECUTE p (1, 2, 3)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Execute::class, $statement);
        self::assertCount(3, $statement->parameters);
    }

    public function testDeallocateLowersAll(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DEALLOCATE ALL');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Deallocate::class, $statement);
        self::assertNull($statement->name);
    }
}
