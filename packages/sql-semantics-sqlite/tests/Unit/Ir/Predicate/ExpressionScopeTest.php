<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(ExpressionScope::class)]
#[Small]
final class ExpressionScopeTest extends TestCase
{
    public function testCheckAcceptsAColumnInTheVisiblePrefix(): void
    {
        $table = new TableSource(new Identifier('main'), new Identifier('entries'), [new Identifier('id')]);
        $column = new ColumnReference(0, 1);

        (new ExpressionScope())->check([$table, $table], [[$column, 0, 2]]);

        self::assertSame(1, $column->source);
    }
}
