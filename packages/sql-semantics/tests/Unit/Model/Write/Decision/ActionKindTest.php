<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Decision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ActionKind::class)]
#[Medium]
final class ActionKindTest extends TestCase
{
    public function testRepresentsEveryMergeOperation(): void
    {
        self::assertSame(['nothing', 'update', 'delete', 'insert'], array_column(ActionKind::cases(), 'value'));
    }

    #[TestWith(['WHEN MATCHED THEN DO NOTHING', ActionKind::Nothing])]
    #[TestWith(['WHEN MATCHED THEN UPDATE SET id=s.id', ActionKind::Update])]
    #[TestWith(['WHEN MATCHED THEN DELETE', ActionKind::Delete])]
    #[TestWith(['WHEN NOT MATCHED THEN INSERT VALUES(s.id)', ActionKind::Insert])]
    #[TestWith(['WHEN NOT MATCHED THEN INSERT DEFAULT VALUES', ActionKind::Insert])]
    public function testDerivesTheOperationFromTheMergeDecision(string $clause, ActionKind $action): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id ' . $clause);
        self::assertInstanceOf(MergeStatement::class, $statement);
        self::assertSame($action, $statement->merge->actions[0]->action);
    }
}
