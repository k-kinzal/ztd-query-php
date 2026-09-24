<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowVersion::class)]
#[Medium]
final class RowVersionTest extends TestCase
{
    public function testRepresentsBothRowImages(): void
    {
        self::assertSame(['old', 'new'], array_column(RowVersion::cases(), 'value'));
    }

    public function testDistinguishesTheOldAndNewRowImagesOfAnUpdate(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t WHEN new.id > old.id BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $when = $statement->when;
        self::assertInstanceOf(BinaryExpression::class, $when);
        self::assertInstanceOf(TriggerColumn::class, $when->left);
        self::assertInstanceOf(TriggerColumn::class, $when->right);
        self::assertSame(RowVersion::New, $when->left->version);
        self::assertSame(RowVersion::Old, $when->right->version);
        self::assertNotSame($when->left->binding->relationId, $when->right->binding->relationId);
        self::assertSame('id', $when->right->binding->column->name);
    }
}
