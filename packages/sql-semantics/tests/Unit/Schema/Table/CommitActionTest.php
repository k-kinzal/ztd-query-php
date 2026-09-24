<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\CommitAction;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommitAction::class)]
#[Medium]
final class CommitActionTest extends TestCase
{
    public function testRepresentsEveryOnCommitBehavior(): void
    {
        self::assertSame(['preserve-rows', 'delete-rows', 'drop'], array_column(CommitAction::cases(), 'value'));
    }

    public function testClassifiesOnCommitAndDefaultsToPreservingRows(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMP TABLE a(id INTEGER) ON COMMIT DELETE ROWS; CREATE TEMP TABLE b(id INTEGER) ON COMMIT DROP; CREATE TEMP TABLE c(id INTEGER)');
        $actions = array_map(static function ($table): CommitAction {
            self::assertInstanceOf(PostgreSqlProperties::class, $table->properties);
            return $table->properties->onCommit;
        }, $schema->tables);
        self::assertSame([CommitAction::DeleteRows, CommitAction::Drop, CommitAction::PreserveRows], $actions);
    }
}
