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
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MatchKind::class)]
#[Medium]
final class MatchKindTest extends TestCase
{
    public function testRepresentsEveryMatchCategory(): void
    {
        self::assertSame(['matched', 'not-matched-by-source', 'not-matched-by-target'], array_column(MatchKind::cases(), 'value'));
    }

    /**
     * @param non-empty-string $expected
     */
    #[TestWith(['WHEN MATCHED THEN DELETE', MatchKind::Matched, 'WHEN MATCHED THEN DELETE'])]
    #[TestWith(['WHEN NOT MATCHED BY SOURCE THEN DELETE', MatchKind::MissingSource, 'WHEN NOT MATCHED BY SOURCE THEN DELETE'])]
    #[TestWith(['WHEN NOT MATCHED THEN INSERT VALUES(s.id)', MatchKind::MissingTarget, 'WHEN NOT MATCHED THEN INSERT VALUES ("s"."id")'])]
    #[TestWith(['WHEN NOT MATCHED BY TARGET THEN INSERT VALUES(s.id)', MatchKind::MissingTarget, 'WHEN NOT MATCHED THEN INSERT VALUES ("s"."id")'])]
    public function testBindsTheCategoryFromTheWhenClause(string $clause, MatchKind $match, string $expected): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id ' . $clause);
        self::assertInstanceOf(MergeStatement::class, $statement);
        self::assertSame($match, $statement->merge->actions[0]->match);
        self::assertStringEndsWith($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
