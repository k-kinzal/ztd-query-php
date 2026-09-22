<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binding\Schema\Alter\AddedColumns;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(AddedColumns::class)]
final class AddedColumnsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testReadIncludesInlineMySqlColumns(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER)', 'ALTER TABLE t ADD COLUMN score INTEGER NOT NULL DEFAULT 7');
        self::assertSame(['id', 'score'], array_column($schema->tables[0]->columns, 'name'));
        self::assertSame(Nullability::NotNull, $schema->tables[0]->columns[1]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $schema->tables[0]->columns[1]->generation);
        self::assertSame('7', $schema->tables[0]->columns[1]->generation->default?->spelling());
    }

    public function testReadDoesNotTreatModifiedColumnsAsAdditions(): void
    {
        $syntax = (new DialectParser(Dialect::MySql))->parse('ALTER TABLE t MODIFY COLUMN id BIGINT');
        self::assertSame([], AddedColumns::read($syntax));
    }
}
