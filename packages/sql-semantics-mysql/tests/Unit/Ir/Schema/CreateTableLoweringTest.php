<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTableLowering;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTableLowering::class)]
#[Medium]
final class CreateTableLoweringTest extends TestCase
{
    public function testLowerFixesTheDeclarationLocation(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (value INTEGER)');
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);

        $value = (new CreateTableLowering())->lower($query->binding(), $query->statement);
        self::assertSame('app', $value->target->name->schema->value);
        self::assertSame('value', $value->columns[0]->name->value);
    }

    public function testLowerUsesTheTemporaryModifierToChooseTheDeclarationNamespace(): void
    {
        $query = (new Inspection())->inspect('CREATE TEMPORARY TABLE other.scratch (n INT)');
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);

        $value = (new CreateTableLowering())->lower($query->binding(), $query->statement);
        self::assertSame('other', $value->target->name->schema->value);
        self::assertSame(ExternalRelationKind::TemporaryTable, $value->target->kind);
    }
}
