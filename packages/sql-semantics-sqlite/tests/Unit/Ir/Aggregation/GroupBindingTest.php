<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupBinding;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(GroupBinding::class)]
#[Medium]
final class GroupBindingTest extends TestCase
{
    public function testKeyResolvesExplicitCollationBeforeDeclaredDefault(): void
    {
        $inspector = new Inspection();
        $table = $inspector->inspect('CREATE TABLE main.items (a COLLATE NOCASE)');
        $operation = $inspector->inspect('SELECT count(*) AS n FROM main.items GROUP BY a COLLATE RTRIM', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $binding = $operation->binding();
        $source = new SourceBinding($binding, $statement->from);
        $key = (new GroupBinding($binding, $source))->key($statement->groupBy[0]);

        self::assertSame(Collation::RightTrim, $key->collation);
        self::assertSame(0, $key->column->slot);
    }
}
