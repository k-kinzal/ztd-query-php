<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Reader;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Reader::class)]
#[Small]
final class ReaderTest extends TestCase
{
    public function testBoundTypesAMarkerOfAPreparedStatementAsAVarchar(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');
        $unbound = Resolved::string(16383, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals([0 => $unbound, 1 => $unbound], (new Reader())->bound($tree, [], true, $session));
    }

    public function testBoundTypesAMarkerByItsValue(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT ? + ?');

        self::assertEquals([0 => Domain::integer()->resolved()], (new Reader())->bound($tree, [[1, Domain::integer()]], false, $session));
        self::assertSame([], (new Reader())->bound($tree, [], false, $session));
    }

    public function testRefusedRecordsAParameterMarkerBeforeALaterSyntaxError(): void
    {
        $session = (new Instance('8.4.7'))->connect();

        $error = (new Reader())->refused(new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, 'x'), 'SELECT ? FROM', false, $session);

        self::assertSame("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near '? FROM' at line 1", $error->getMessage());
        self::assertSame([['Error', 1064, $error->getMessage()]], $session->diagnostics->conditions);
    }

    public function testReadRecordsTheHintCommentsAndRefusesAParameterMarkerOutsideAPreparedStatement(): void
    {
        $session = (new Instance())->connect();
        (new Reader())->read('SELECT /*+ BKA() */ ?', true, $session);

        self::assertTrue($session->commented);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);

        (new Reader())->read('SELECT ?', false, $session);
    }

    public function testRefusalAnswersTheParseErrorOfARefusedStatement(): void
    {
        $session = (new Instance())->connect();
        $tree = $session->semantics()->parser()->parse('SELECT 1');

        self::assertSame(1064, (new Reader())->refusal($tree, new AnalysisException('refused'), 'SELECT 1', '', $session)->getCode());
    }
}
