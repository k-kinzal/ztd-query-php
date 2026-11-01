<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\XaInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier;

#[CoversClass(XaInput::class)]
#[Small]
final class XaInputTest extends TestCase
{
    public function testErrorQuotesTheExplicitFormatOperand(): void
    {
        $session = (new Instance())->connect();
        $session->text = "XA START 'g','b',1.5 JOIN";
        $error = (new XaInput())->error(new RefusedXaIdentifier(17), $session);

        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near '1.5 JOIN' at line 1", $error->getMessage());
    }

    public function testErrorSkipsCommentsBeforeTheFollowingToken(): void
    {
        $session = (new Instance())->connect();
        $session->text = "XA START 'g' /* gap */ JOIN";
        $error = (new XaInput())->error(new RefusedXaIdentifier(12, true), $session);

        self::assertStringEndsWith("near 'JOIN' at line 1", $error->getMessage());
    }
}
