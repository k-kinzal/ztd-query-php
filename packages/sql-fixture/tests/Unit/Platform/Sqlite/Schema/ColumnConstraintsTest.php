<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\ColumnConstraints as Subject;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use Tests\Statement\SqliteStatements;

#[CoversClass(Subject::class)]
final class ColumnConstraintsTest extends TestCase
{
    public function testReadDefaultsToAPlainColumn(): void
    {
        $constraints = (new Subject())->read(SqliteStatements::columns('a INT NOT NULL')[0]->constraints);

        self::assertFalse($constraints->primaryKey);
        self::assertFalse($constraints->autoIncrement);
        self::assertNull($constraints->default);
    }

    public function testReadRecognizesPrimaryKeyAutoincrementAndDefault(): void
    {
        $constraints = array_map(static fn ($column): array => $column->constraints, SqliteStatements::columns("id INTEGER NOT NULL DEFAULT 'x' PRIMARY KEY AUTOINCREMENT, k INT PRIMARY KEY, w TEXT DEFAULT ready"));

        $id = (new Subject())->read($constraints[0]);
        self::assertTrue($id->primaryKey);
        self::assertTrue($id->autoIncrement);
        self::assertInstanceOf(DefaultLiteral::class, $id->default);
        self::assertSame([true, false], [(new Subject())->read($constraints[1])->primaryKey, (new Subject())->read($constraints[1])->autoIncrement]);
        self::assertInstanceOf(DefaultWord::class, (new Subject())->read($constraints[2])->default);
    }

    public function testReadLetsTheLastDefaultDecide(): void
    {
        $constraints = array_map(static fn ($column): array => $column->constraints, SqliteStatements::columns("a INT DEFAULT 1 DEFAULT (1 + 1), b TEXT DEFAULT (1) DEFAULT 'x'"));

        self::assertNull((new Subject())->read($constraints[0])->default);
        self::assertInstanceOf(DefaultLiteral::class, (new Subject())->read($constraints[1])->default);
    }
}
