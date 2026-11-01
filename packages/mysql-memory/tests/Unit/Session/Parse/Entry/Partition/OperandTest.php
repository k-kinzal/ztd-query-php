<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Partition;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Partition\Operand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Source\SourceMap;

#[CoversClass(Operand::class)]
#[Small]
final class OperandTest extends TestCase
{
    public function testDatabasePrecedesAModernPartitionSubqueryRefusal(): void
    {
        $session = (new Instance())->connect();
        $sql = 'ALTER TABLE t PARTITION BY HASH((SELECT 1))';
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $failure = new RejectedInput(new M\Partition\Problem\RefusedScalar($query, M\Partition\Problem\OperandCondition::Subquery), strlen($sql));
        self::assertSame(1046, (new Operand())->database($failure, $sql, $session)?->getCode());
        self::assertNull((new Operand())->database($failure, str_replace('TABLE t', 'TABLE d.t', $sql), $session));
    }

    public function testDatabasePrecedesAnIntrinsicMethodCondition(): void
    {
        $session = (new Instance())->connect();
        $sql = 'ALTER TABLE t PARTITION BY HASH(USER())';
        $failure = new RejectedInput(new M\Partition\Problem\InvalidPartitionExpression(new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])), strlen($sql));
        self::assertSame(1046, (new Operand())->database($failure, $sql, $session)?->getCode());
        self::assertNull((new Operand())->database($failure, str_replace('TABLE t', 'TABLE d.t', $sql), $session));
    }

    public function testErrorPreservesUnknownVariableLookupBeforeThePartitionRole(): void
    {
        $session = (new Instance())->connect();
        $problem = new M\Partition\Problem\InvalidPartitionExpression(new M\Variable\SystemVariable(new Name('unknown_partition_variable')));
        self::assertSame(1193, (new Operand())->error($problem, new SourceMap(), $session)->getCode());
    }

    public function testNoticesPreserveContextualDeprecation(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $call = new M\Call\FunctionCall(new Name('FOUND_ROWS'));
        $notices = (new Operand())->notices(new M\Partition\Problem\InvalidPartitionExpression($call), $session);
        self::assertCount(1, $notices);
        self::assertSame(1287, $notices[0]->code());
    }

    public function testFactsLeaveModernSubqueriesUncontextualized(): void
    {
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Variable\SystemVariable(new Name('unknown_partition_variable')))]));
        $problem = new M\Partition\Problem\InvalidPartitionExpression($query);
        self::assertSame([], (new Operand())->facts($problem, (new Instance('8.4.7'))->connect())->diagnostics);
        self::assertInstanceOf(M\Variable\Problem\UnknownSystemVariable::class, (new Operand())->facts($problem, (new Instance('5.7.44'))->connect())->diagnostics[0]);
    }

    public function testOrderedKeepsAnEngineRefusalAheadOfOperandWarnings(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $sql = 'ALTER TABLE d.t ENGINE=missing PARTITION BY HASH(USER())';
        $failure = new RejectedInput(new M\Partition\Problem\InvalidPartitionExpression(new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])), strlen($sql));
        /** @var list<array{int, M\Notice\Deprecation}> $completed */
        $completed = [];
        [$notices, $error] = (new Operand())->ordered($failure, $completed, StatementError::ParseError->error('', 1), $sql, $session);
        self::assertSame([], $notices);
        self::assertSame(1286, $error->getCode());
    }
}
