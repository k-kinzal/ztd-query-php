<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Statement\Operation;

#[CoversClass(Star::class)]
#[Medium]
final class StarTest extends TestCase
{
    public function testRenderWritesTheStarAmongOtherColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('select *, a, * from t', [$create]);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(Star::class, $query->statement->columns[0]);
        self::assertSame('SELECT *, a, * FROM t', $query->toString());
        self::assertSame(['a', 'b', 'a', 'a', 'b'], array_map(static fn (object $field): ?string => $field->name?->value, [...$query->fields() ?? []]));
    }

    public function testRenderWritesANewlyBuiltStarWithoutInput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new Star()]));

        self::assertSame('SELECT *', $operation->toString());
        self::assertSame(MisuseRule::StarWithoutTables->value, $operation->facts->diagnostics[0]->message());
    }
}
