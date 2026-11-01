<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Program\Variable;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Variable::class)]
#[Small]
final class VariableTest extends TestCase
{
    public function testAssignStoresAsIntoAColumnNamedAfterTheVariable(): void
    {
        $session = (new Instance())->connect();
        $variable = new Variable('v', Domain::integer(Field::Tiny, 4));
        $context = new Context(new SqlModes([]), new Diagnostics(), $session->variables, 0.0);

        $variable->assign(300, Domain::integer(), $context);

        self::assertSame([127, [['Warning', 1264, "Out of range value for column 'v' at row 1"]]], [$variable->value, $context->diagnostics->conditions]);
    }

    public function testAssignRefusesUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $variable = new Variable('v', Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')));

        $this->expectExceptionCode(1406);
        $this->expectExceptionMessage("Data too long for column 'v' at row 1");

        $variable->assign('abc', Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), new Context($session->modes(), new Diagnostics(), $session->variables, 0.0, true));
    }
}
