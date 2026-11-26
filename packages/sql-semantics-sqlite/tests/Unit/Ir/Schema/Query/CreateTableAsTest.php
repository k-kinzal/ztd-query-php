<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAs;

#[CoversClass(CreateTableAs::class)]
#[Small]
final class CreateTableAsTest extends TestCase
{
    public function testColumnsDeriveNamesAndTypesFromValues(): void
    {
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('9')])]);
        $table = new CreateTableAs(new Identifier('main'), new Identifier('sample'), $query);

        self::assertSame('column1', $table->columns()[0]->name->value);
        self::assertSame(CreatedColumnDeclaration::Untyped, $table->columns()[0]->type);
    }

    public function testToStringEmitsTheCompleteGuardedPopulation(): void
    {
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('9')])]);
        $table = new CreateTableAs(new Identifier('temp'), new Identifier('sample'), $query, true);

        self::assertSame('CREATE TABLE IF NOT EXISTS "temp"."sample" AS VALUES (9)', $table->toString());
    }

    public function testProfileIdMatchesTheCheckedQueryProfile(): void
    {
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('9')])]);
        $table = new CreateTableAs(new Identifier('main'), new Identifier('sample'), $query);

        self::assertSame($query->profileId(), $table->profileId());
    }
}
