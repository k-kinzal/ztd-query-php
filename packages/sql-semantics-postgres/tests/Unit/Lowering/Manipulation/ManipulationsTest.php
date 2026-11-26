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
        $profile = new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172);
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Text)]);
        $u = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('u')), $profile, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int8, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Bool, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull)]);
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Prepare::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Execute::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Deallocate::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\DeclareCursor::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Fetch::class, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Close::class], array_map(static fn (string $sql): string => $semantics->inspect($sql)->statement::class, ['INSERT INTO t VALUES (1)', 'UPDATE t SET a = 1', 'DELETE FROM t', 'MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE', 'COPY t FROM STDIN', 'PREPARE p AS SELECT 1', 'EXECUTE p', 'DEALLOCATE p', 'DECLARE c CURSOR FOR SELECT 1', 'FETCH c', 'CLOSE c']));
    }

    public function testPreparableForwardsAQueryToTheQueryFamily(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->inspect('WITH x AS (SELECT 1 AS k) SELECT k FROM x');
        self::assertSame('k', $query->field(0)->name?->value);
    }

    public function testPreparableLowersADataModifyingStatement(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection('pg-17.2');
        $query = $semantics->inspect('PREPARE p AS MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Prepare::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement->statement);
    }
}
