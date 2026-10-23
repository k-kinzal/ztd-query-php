<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Definition\CreationQueryUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreationQueryUse::class)]
#[Small]
final class CreationQueryUseTest extends TestCase
{
    public function testExecuteUsesTheSameRetainedQueryAcrossDestinationChanges(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(7)');
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), query: new M\Table\TableQuery(new M\Query\Select([], [new M\Query\Star()])), ifNotExists: true);
        $use = new CreationQueryUse();
        $session->text = 'unrelated invalid SQL';
        self::assertSame(0, $use->execute($definition, $session)->affectedRows);
        $session->query('DROP TABLE t');
        $session->text = 'unrelated invalid SQL';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1096);
        $use->execute($definition, $session);
    }

    public function testBoundKeepsTheOriginalQueryWithoutConstructingAReadStatement(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $query = new M\Query\Select([], [new M\Query\Star()]);
        $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), query: new M\Table\TableQuery($query));
        $bound = (new CreationQueryUse())->bound($definition, $session);
        self::assertSame($query, $bound->statement);
        self::assertNotEmpty($bound->facts->diagnostics);
    }
}
