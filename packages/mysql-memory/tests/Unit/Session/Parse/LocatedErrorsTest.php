<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\LocatedErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(LocatedErrors::class)]
#[Small]
final class LocatedErrorsTest extends TestCase
{
    public function testErrorQuotesTheOriginalSpellingAndLine(): void
    {
        $session = (new Instance())->connect();
        $session->text = "ALTER TABLE missing\nPARTITION BY HASH ( /* marker */ (USER ( )))";
        $operation = $session->analyze($session->text);
        $problems = array_values(array_filter($operation->facts->diagnostics, static fn (Diagnostic $problem): bool => $problem instanceof InvalidPartitionExpression));
        $error = (new LocatedErrors())->error($problems[0], $operation, $session);
        self::assertNotNull($error);
        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near '(USER ( )))' at line 2", $error->getMessage());
    }

    public function testErrorIgnoresUnrelatedDiagnostics(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT missing');
        self::assertNull((new LocatedErrors())->error($operation->facts->diagnostics[0], $operation, $session));
    }

    public function testNamesLocatesTheOperatorOfTheRefusedAssignment(): void
    {
        $session = (new Instance())->connect();
        $session->text = "SET @a = 'NAMES = 1', NAMES\n/* marker */ := 'utf8'";
        $operation = $session->analyze($session->text);
        $problem = $operation->facts->diagnostics[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse::class, $problem);

        $error = (new LocatedErrors())->names($problem, $operation, $session);
        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near ':= 'utf8'' at line 2", $error->getMessage());
    }
}
