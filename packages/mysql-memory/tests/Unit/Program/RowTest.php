<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Row;
use MySqlMemory\Program\Variable;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;

#[CoversClass(Row::class)]
#[Small]
final class RowTest extends TestCase
{
    public function testVariableFindsTheFirstVariableOfANameWithoutRegardToCase(): void
    {
        $first = new Variable('a', Domain::integer());
        $row = new Row(new ParameterList([]), [$first, new Variable('A', Domain::integer())]);

        self::assertSame([$first, null], [$row->variable('A'), $row->variable('b')]);
    }

    public function testRowAnswersTheNamesTypesAndAliasAsSqlSemanticsReadsThem(): void
    {
        $relation = new ParameterList([]);
        $row = (new Row($relation, [new Variable('a', Domain::integer())], 'NEW', true))->row();

        self::assertSame([$relation, 'a', 'NEW'], [$row->relation, $row->names[0]->value, $row->alias?->value]);
    }
}
