<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns::class)]
#[Medium]
final class KeyColumnsTest extends TestCase
{
    public function testPositionFindsTheSlotOfAName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('SELECT 1', $context);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        self::assertSame(2, (new \SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns())->position($derivation, (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Targets())->shape($context[0]), new \SqlSemantics\Statement\Identifier\Name('c')));
    }

    public function testReportReportsANameAShapeLacks(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (a int, UNIQUE (a, zz))', $context);
        self::assertSame([
          0 => 'column "zz" named in key does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }
}
