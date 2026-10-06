<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewColumns;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(ViewColumns::class)]
#[Medium]
final class ViewColumnsTest extends TestCase
{
    public function testNamesRenamesGeneratedNamesThatAreNotValidOrRepeated(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $view = $semantics->analyze("CREATE VIEW v AS SELECT 'a ', 1+1, '', 'b', 1+1 AS c, ' x', 'Name_exp_1'", []);
        $query = $semantics->analyze('SELECT * FROM v', [$view]);

        self::assertSame(['Name_exp_1', '1+1', 'Name_exp_3', 'b', 'c', 'x', 'Name_exp_Name_exp_1'], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
    }

    public function testNamesUsesTheLegacyPrefixIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $view = $semantics->analyze("CREATE VIEW v AS SELECT 'Name_exp_2', ''", []);

        self::assertSame(['Name_exp_2', 'My_exp_'], array_map(static fn (Field $field): ?string => $field->name?->value, $semantics->analyze('SELECT * FROM v', [$view])->fields()->items ?? []));
    }

    public function testGeneratedTellsTheFieldsWithoutAliasThatAreNoColumns(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a, b AS c, 1+1 FROM t');
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame([false, false, true], (new ViewColumns($operation->context->profile, $operation->context->columnNames))->generated($select, $operation->fields()->items ?? []));
    }

    public function testBlockAnswersTheFirstQueryBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('(SELECT 1 AS a) UNION SELECT 2');
        $values = (new Semantics(Dialect::MySql))->analyze('VALUES ROW(1)')->statement;
        $columns = new ViewColumns($operation->context->profile, $operation->context->columnNames);

        self::assertInstanceOf(Query::class, $operation->statement);
        self::assertInstanceOf(Query::class, $values);
        self::assertSame('a', $columns->block($operation->statement)?->items[0]->alias?->value);
        self::assertNull($columns->block($values));
    }

    public function testUniqueAddsTheCounterWhenTheNameIsTaken(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1');
        $columns = new ViewColumns($operation->context->profile, $operation->context->columnNames);

        self::assertSame('Name_exp_1_a', $columns->unique(['Name_exp_a', 'a', 'a'], 2, 2, 'a'));
    }

    public function testCutKeepsSixtyFourCharactersOrTheLegacyBytes(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1');
        $columns = new ViewColumns($operation->context->profile, $operation->context->columnNames);

        self::assertSame(str_repeat('a', 64), $columns->cut(str_repeat('a', 70), false));
        self::assertSame(str_repeat('a', 191), $columns->cut(str_repeat('a', 200), true));
        self::assertEquals(new Name('x'), new Name($columns->cut('x', false)));
    }
}
