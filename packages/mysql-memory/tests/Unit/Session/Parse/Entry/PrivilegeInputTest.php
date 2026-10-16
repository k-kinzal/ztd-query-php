<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\PrivilegeInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrivilegeInput::class)]
#[Small]
final class PrivilegeInputTest extends TestCase
{
    public function testPlacementFormatsImmediateGrantRefusals(): void
    {
        $input = new PrivilegeInput();

        self::assertSame(3835, $input->placement(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege('AS clause', 'database', 'ER_UNSUPPORTED_USE_OF_GRANT_AS'), (new Instance())->connect())?->getCode());
        self::assertSame(1144, $input->placement(new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege('SELECT', 'routine', 'ER_ILLEGAL_GRANT_FOR_TABLE'), (new Instance())->connect())?->getCode());
    }

    public function testLevelUsesTypedTargetsWithoutReadingRequestText(): void
    {
        $request = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel($request);
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        self::assertSame(1046, (new PrivilegeInput())->level($problem, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1144, (new PrivilegeInput())->level($problem, $session)->getCode());
    }

    public function testColumnsReportsTheDeferredInputPosition(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->text = "GRANT\nSELECT(a) ON FUNCTION d.p TO u";
        $problem = new \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege(strlen($session->text), true);
        $error = (new PrivilegeInput())->columns($problem, $session);

        self::assertSame(1064, $error->getCode());
        self::assertStringContainsString("near '' at line 2", $error->getMessage());
        $session->text .= ';';
        self::assertSame($error->getMessage(), (new PrivilegeInput())->columns($problem, $session)->getMessage());
    }

    public function testDatabasePrecedesAnInvalidPrivilegeAndRecipient(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query("GRANT r@h ON PROCEDURE * TO u@'bad@host'");
    }
}
