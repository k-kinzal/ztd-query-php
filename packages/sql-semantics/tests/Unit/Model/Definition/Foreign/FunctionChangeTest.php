<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FunctionChange::class)]
#[Medium]
final class FunctionChangeTest extends TestCase
{
    public function testOmittedAndRemovedFunctionsHaveDifferentMeanings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertSame(FunctionChange::Remove, $statement->handler);
        self::assertSame(FunctionChange::Keep, $statement->validator);
    }

}
