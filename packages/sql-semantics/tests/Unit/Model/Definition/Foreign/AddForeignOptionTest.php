<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddForeignOption::class)]
#[Medium]
final class AddForeignOptionTest extends TestCase
{
    public function testRequiredOptionRetainsBothItsNameAndText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER FOREIGN DATA WRAPPER fdw OPTIONS (ADD format 'csv')");
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        self::assertInstanceOf(AddForeignOption::class, $statement->options[0]);
        self::assertSame('format', $statement->options[0]->option->name);
        self::assertSame("'csv'", $statement->options[0]->option->value->text);
    }

}
