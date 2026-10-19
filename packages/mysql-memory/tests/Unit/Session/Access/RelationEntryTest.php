<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\RelationEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationEntry::class)]
#[Medium]
final class RelationEntryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerInputs(): iterable
    {
        yield 'projection before input' => ['SELECT @@missing FROM t QUALIFY 1', 1193];
        yield 'input before predicate' => ['SELECT 1 FROM t QUALIFY @@missing', 1046];
        yield 'input before alias collision' => ['WITH c AS (SELECT 1 QUALIFY ROW(1,2)) UPDATE t,t SET a=1', 1046];
        yield 'active common table' => ['WITH c AS (SELECT 1 FROM t QUALIFY 1) SELECT * FROM c', 1046];
    }

    public function testNodesFollowsOnlyTheResolvedCommonTableDefinitions(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze('WITH unused AS (SELECT * FROM ignored), active AS (SELECT * FROM reached) SELECT * FROM active', []);
        $nodes = (new RelationEntry())->nodes($operation->statement, $operation->facts);
        $tables = array_filter($nodes, static fn ($node): bool => $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference);
        self::assertSame(['active', 'reached'], array_values(array_map(static fn ($table): string => $table->name->name->value, $tables)));
    }

    #[DataProvider('providerInputs')]
    public function testCheckOrdersTheRetainedNamesBeforeTheLaterCondition(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze($sql, $session->semantics()->context([], true));
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new RelationEntry())->check($operation->statement, $operation->facts, $session);
    }
}
