<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\Manipulations::class)]
#[Medium]
final class ManipulationsTest extends TestCase
{
    public function testStatementRoutesEveryStatementOfTheFamily(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Statement\Declaration\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Statement\Type\Nullability::NotNull), new \SqlSemantics\Statement\Declaration\Column(new \SqlSemantics\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, \SqlSemantics\Platform\PostgreSql\Statement\Prepared\Prepare::class, \SqlSemantics\Platform\PostgreSql\Statement\Prepared\Execute::class, \SqlSemantics\Platform\PostgreSql\Statement\Prepared\Deallocate::class, \SqlSemantics\Platform\PostgreSql\Statement\Cursor\DeclareCursor::class, \SqlSemantics\Platform\PostgreSql\Statement\Cursor\Fetch::class, \SqlSemantics\Platform\PostgreSql\Statement\Cursor\Close::class], array_map(static fn (string $sql): string => $semantics->analyze($sql)->statement::class, ['INSERT INTO t VALUES (1)', 'UPDATE t SET a = 1', 'DELETE FROM t', 'MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE', 'COPY t FROM STDIN', 'PREPARE p AS SELECT 1', 'EXECUTE p', 'DEALLOCATE p', 'DECLARE c CURSOR FOR SELECT 1', 'FETCH c', 'CLOSE c']));
    }

    public function testPreparableForwardsAQueryToTheQueryFamily(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('WITH x AS (SELECT 1 AS k) SELECT k FROM x');
        self::assertSame('k', $query->field(0)->name?->value);
    }

    public function testPreparableLowersADataModifyingStatement(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('PREPARE p AS MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Prepared\Prepare::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement->statement);
    }
}
