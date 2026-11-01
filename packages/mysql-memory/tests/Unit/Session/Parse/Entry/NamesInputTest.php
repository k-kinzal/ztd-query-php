<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\NamesInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;

#[CoversClass(NamesInput::class)]
#[Small]
final class NamesInputTest extends TestCase
{
    public function testErrorStopsAtTheFirstOfSeveralInvalidBytes(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = "SET NAMES = 1 ]\x1e";

        self::assertStringEndsWith("near ']\x1e' at line 1", (new NamesInput())->error(new RefusedNamesAssignment(10, 13), $session)->getMessage());
    }

    public function testErrorLocatesTheOperatorFromItsRetainedOffset(): void
    {
        $session = (new Instance())->connect();
        $session->text = "SET @a = 'NAMES = 1', NAMES\n/* marker */ := 'utf8'";
        $offset = strpos($session->text, ':=');
        self::assertNotFalse($offset);
        $error = (new NamesInput())->error(new RefusedNamesAssignment($offset, strlen($session->text)), $session);

        self::assertSame(1064, $error->getCode());
        self::assertStringEndsWith("near ':= 'utf8'' at line 2", $error->getMessage());
    }

    public function testErrorQuotesTheUnreadTailOnMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = 'SET NAMES = !1, @a = !2';

        self::assertStringEndsWith("near ' @a = !2' at line 1", (new NamesInput())->error(new RefusedNamesAssignment(10, 14), $session)->getMessage());
    }

    public function testErrorReachesTheEndOfTheInputOnMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = "SET NAMES\n= 'utf8'";

        self::assertStringEndsWith("near '' at line 2", (new NamesInput())->error(new RefusedNamesAssignment(10, strlen($session->text)), $session)->getMessage());
    }

    public function testErrorUsesTheRetainedShadowingFact(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated input';
        $error = (new NamesInput())->error(new RefusedNamesAssignment(10, 13, true), $session);

        self::assertSame(1453, $error->getCode());
        self::assertSame('Variable \'names\' must be quoted with `...`, or renamed', $error->getMessage());
    }
}
