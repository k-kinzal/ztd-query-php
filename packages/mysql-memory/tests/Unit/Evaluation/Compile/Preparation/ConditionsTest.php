<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Preparation\Conditions;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Conditions::class)]
#[Small]
final class ConditionsTest extends TestCase
{
    public function testCheckKeepsTheSameStructureAndItsPreparationWarning(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SELECT 1 WHERE CAST('123' AS DECIMAL(1))", true);
        $session->text = 'unrelated invalid input';
        (new Preparation())->check($operation, $session);
        self::assertSame([['Warning', 1264, "Out of range value for column '(null)' at row 1"]], $session->diagnostics->conditions);
        self::assertNotNull($session->preparation->conditions);
        self::assertSame(1, count($session->preparation->conditions));
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testRetainHonorsShortCircuitingAndDoesNotRepeatWarningsAtExecute(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE p FROM \"SELECT 1 WHERE 0 AND CAST('123' AS DECIMAL(1))\"");
        self::assertSame([], $session->diagnostics->conditions);
        $result = $session->query('EXECUTE p')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
