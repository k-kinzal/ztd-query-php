<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\Pending;
use MySqlMemory\Command\Prepared\PendingRefusal;
use MySqlMemory\Command\Prepared\PendingTable;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Pending::class)]
#[Medium]
final class PendingTest extends TestCase
{
    public function testParametersOfAPendingTable(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $pending = new PendingTable(new M\Table\Problem\Creation\RefusedCreateTail(new QualifiedName(new Name('t')), [], [], 0, false, new M\Partition\Problem\RefusedScalar($row)), 'd');

        self::assertSame([], $pending->parameters()->types);
    }

    public function testParametersOfAPendingRefusal(): void
    {
        $sql = 'REVOKE ALL ON PROCEDURE * FROM u';
        try {
            (new Instance())->connect()->semantics()->analyze($sql);
            self::fail('ALL at the database level of a routine kind is refused.');
        } catch (RejectedInput $failure) {
            self::assertSame([], (new PendingRefusal($sql, $failure))->parameters()->types);
        }
    }

    public function testExecuteOfAPendingRefusalRaisesItsCondition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $sql = 'REVOKE ALL ON PROCEDURE * FROM u';
        try {
            $session->semantics()->analyze($sql);
            self::fail('ALL at the database level of a routine kind is refused.');
        } catch (RejectedInput $failure) {
            $pending = new PendingRefusal($sql, $failure);
        }
        $this->expectExceptionCode(1144);
        $pending->execute($session, 'EXECUTE s', []);
    }
}
