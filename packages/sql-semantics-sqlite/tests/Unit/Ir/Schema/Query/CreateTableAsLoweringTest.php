<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreatedColumnDeclaration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateTableAsLowering::class)]
#[Small]
final class CreateTableAsLoweringTest extends TestCase
{
    public function testLowerResolvesTheDestinationAndPopulation(): void
    {
        $operation = (new Inspection())->inspect('CREATE TEMP TABLE sample AS SELECT CAST(7 AS REAL) AS result');
        self::assertInstanceOf(CreateTableAs::class, $operation->statement);

        $table = (new CreateTableAsLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame('temp', $table->schema->value);
        self::assertSame('result', $table->columns()[0]->name->value);
        self::assertSame(CreatedColumnDeclaration::Real, $table->columns()[0]->type);
    }
}
