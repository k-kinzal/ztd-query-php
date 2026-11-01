<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingTable;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(PendingTable::class)]
#[Medium]
final class PendingTableTest extends TestCase
{
    /**
     * @return iterable<array{PendingTable}>
     */
    public static function providerIncompleteDefinitions(): iterable
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        yield [new PendingTable(new M\Table\Problem\Creation\RefusedCreateTail(new QualifiedName(new Name('t')), [], [], 0, false, new M\Partition\Problem\RefusedScalar($row)), 'd')];
    }

    #[DataProvider('providerIncompleteDefinitions')]
    public function testParametersHasNoMarkersBeforeTheRefusedTail(PendingTable $definition): void
    {
        self::assertSame([], $definition->parameters()->types);
    }

    #[DataProvider('providerIncompleteDefinitions')]
    public function testExecuteUsesThePreparationDatabaseForTheIncompleteHeader(PendingTable $definition): void
    {
        $session = (new Instance('8.4.7', [], ['other']))->connect(database: 'other');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'd'");
        $definition->execute($session, 'opaque incomplete request', []);
    }

    #[DataProvider('providerIncompleteDefinitions')]
    public function testRunReachesThePartitionRefusalOnceItsDatabaseExists(PendingTable $definition): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $reply = $definition->run($session, 'opaque incomplete request', [])[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1564, $reply->getCode());
        self::assertSame([['Error', 1564, 'This partition function is not allowed']], $session->diagnostics->conditions);
    }
}
