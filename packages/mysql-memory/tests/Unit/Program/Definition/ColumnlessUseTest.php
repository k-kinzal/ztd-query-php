<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Definition\ColumnlessUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ColumnlessUse::class)]
#[Small]
final class ColumnlessUseTest extends TestCase
{
    public function testExecuteChecksTheSameInstructionAgainstTheExistingTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT)');
        $definition = new CreateTable(new QualifiedName(new Name('t')), ifNotExists: true);
        $use = new ColumnlessUse();
        self::assertSame(0, $use->execute($definition, $session, 'd')->affectedRows);
        $session->query('DROP TABLE d.t');
        $session->text = 'unrelated invalid SQL';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4028);
        $use->execute($definition, $session, 'd');
    }

    public function testSchemaResolvesTheRetainedDestination(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        self::assertSame($session->instance->dictionary->schema('d'), (new ColumnlessUse())->schema(new CreateTable(new QualifiedName(new Name('t'))), 'd', $session));
    }

    public function testColumnsChecksTheRetainedIndexBeforeMissingVisibleColumns(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1072);
        $session->query('CREATE TABLE d.t(INDEX(a))');
    }
}
