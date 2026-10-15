<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\InputErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

#[CoversClass(InputErrors::class)]
#[Small]
final class InputErrorsTest extends TestCase
{
    public function testErrorLocatesACompletedInternalEntry(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->text = "/* prefix */\nPARSE_GCOL_EXPR(missing)";
        $problem = new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry(13);

        self::assertStringContainsString("near 'PARSE_GCOL_EXPR(missing)' at line 2", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorFormatsARefusedSourceNumber(): void
    {
        $session = (new Instance())->connect();
        $session->text = '2 TRAILING';
        $problem = new RefusedSourceNumber(SourceOptionKind::GtidOnly, new Numeral('2'), 0);

        self::assertStringContainsString("near '2 TRAILING' at line 1", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorLocatesTheRefusedSubqueryWithoutKeepingItsText(): void
    {
        $session = (new Instance())->connect();
        $session->text = "PURGE BINARY LOGS BEFORE\n(SELECT 1)";
        $problem = new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery(25);

        self::assertStringContainsString("near '(SELECT 1)' at line 2", (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorFormatsARefusedRole(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'SELECT TO user';
        $problem = new RoleOrPrivilegeMismatch('SELECT', true, 0);

        self::assertStringContainsString('Illegal authorization identifier', (new InputErrors())->error($problem, $session)?->getMessage() ?? '');
    }

    public function testErrorNamesTheEmptyObjectRoleWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $errors = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind $kind): ?int => (new InputErrors())->error(new \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName($kind), $session)?->getCode(), \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::cases());

        self::assertSame([1102, 1103], $errors);
        self::assertSame('', $session->text);
    }

    public function testErrorLeavesOtherDiagnosticsToTheirFamily(): void
    {
        self::assertNull((new InputErrors())->error(new NotSupportedYet('feature'), (new Instance())->connect()));
    }
}
