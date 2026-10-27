<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\LocatedErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;

#[CoversClass(LocatedErrors::class)]
#[Small]
final class LocatedErrorsTest extends TestCase
{
    public function testSubqueryUsesTheNestedOccurrenceInsteadOfTheEnclosingCall(): void
    {
        $session = (new Instance())->connect();
        $sql = 'SELECT ABS((SELECT 1))';
        $operation = $session->semantics()->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $expression = $operation->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Statement\Operand::class, $expression);
        self::assertSame(strpos($sql, '(SELECT'), (new LocatedErrors())->subquery($expression, null));
        self::assertNull((new LocatedErrors())->subquery(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), null));
    }

    public function testErrorQuotesTheOriginalSpellingAndLine(): void
    {
        $session = (new Instance())->connect();
        $session->text = "ALTER TABLE missing\nPARTITION BY HASH ( /* marker */ (USER ( )))";
        try {
            $session->semantics()->analyze($session->text);
            self::fail('Session-dependent partition methods have no constructible interpretation.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(InvalidPartitionExpression::class, $failure->problem);
            $error = (new LocatedErrors())->located($failure->problem, $failure->sources, $session);
        }
        self::assertNotNull($error);
        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near '(USER ( )))' at line 2", $error->getMessage());
    }

    public function testErrorIgnoresUnrelatedDiagnostics(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT missing');
        self::assertNull((new LocatedErrors())->error($operation->statementInformation()->diagnostics[0], $operation, $session));
    }

    public function testLocatedUsesTheRefusedDefinitionsOriginalOccurrence(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->run("CREATE TABLE t\nPARTITION BY HASH( ( USER ( ) ) )");

        self::assertSame([['Error', 1064, "Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed near '( USER ( ) ) )' at line 2"]], $session->diagnostics->conditions);
    }

}
