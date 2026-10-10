<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\IndexOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IndexOptions::class)]
#[Small]
final class IndexOptionsTest extends TestCase
{
    public function testVisibleUsesTheLastWrittenOption(): void
    {
        $rules = new IndexOptions();
        $part = new ColumnPart(new Name('a'));

        self::assertTrue($rules->visible(new IndexDefinition(IndexKind::Index, [$part])));
        self::assertFalse($rules->visible(new IndexDefinition(IndexKind::Index, [$part], options: [new IndexVisibility(true), new IndexVisibility(false)])));
        self::assertTrue($rules->visible(new IndexDefinition(IndexKind::Index, [$part], options: [new IndexVisibility(false), new IndexVisibility(true)])));
    }

    public function testChangeReplacesVisibilityWithoutChangingTheIndexIdentity(): void
    {
        $index = new IndexDefinition(IndexKind::Unique, [new ColumnPart(new Name('a'))], new ColumnName(new Name('k')), options: [new IndexVisibility(false), new IndexVisibility(true)]);
        $changed = (new IndexOptions())->change($index, false);

        self::assertSame($index->parts, $changed->parts);
        self::assertSame($index->name, $changed->name);
        self::assertSame(IndexKind::Unique, $changed->kind);
        self::assertEquals([new IndexVisibility(false)], $changed->options);
    }

    public function testCheckRejectsHidingTheImplicitPrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT NOT NULL,UNIQUE KEY k(a))');
        $this->expectExceptionMessage('A primary key index cannot be invisible');

        $session->query('ALTER TABLE t ALTER INDEX k INVISIBLE');
    }
}
