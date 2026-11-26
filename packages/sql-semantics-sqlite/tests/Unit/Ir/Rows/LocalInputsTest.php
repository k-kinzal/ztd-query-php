<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalInputs;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Rows\LocalInputs
 */
final class LocalInputsTest extends TestCase
{
    public function testOccurrencesListTheSameDefinitionForEachLocalInput(): void
    {
        $local = new LocalSource(new ValuesQuery([new ValueRow([new IntegerLiteral('4')])]), [new Identifier('id')]);
        $join = new Join(JoinKind::Inner, $local);

        self::assertSame([$local, $local], (new LocalInputs())->occurrences($local, [$join]));
    }

    public function testCheckedReturnsTheDefinitionEnteredByTheJoin(): void
    {
        $local = new LocalSource(new ValuesQuery([new ValueRow([new IntegerLiteral('4')])]), [new Identifier('id')]);
        $table = new TableSource(new Identifier('main'), new Identifier('t'), []);

        self::assertSame($local, (new LocalInputs())->checked($table, [new Join(JoinKind::Cross, $local)]));
    }
}
