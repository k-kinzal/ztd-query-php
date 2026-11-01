<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Dml;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Dml\HandlerInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler as H;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(HandlerInput::class)]
#[Small]
final class HandlerInputTest extends TestCase
{
    public function testErrorKeepsTheSameOperandAcrossHandlerStateChanges(): void
    {
        $read = new H\RefusedRead(new H\OpenHandler(new Name('h')), H\ScanDirection::First, where: new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1109, (new HandlerInput())->error($read, $session)->getCode());
        $session->query('HANDLER t OPEN AS h');
        $session->text = 'unrelated invalid input';
        self::assertSame(1241, (new HandlerInput())->error($read, $session)->getCode());
        $session->query('HANDLER h CLOSE');
        $session->text = 'unrelated invalid input';
        self::assertSame(1109, (new HandlerInput())->error($read, $session)->getCode());
    }

    public function testResolvePreparesWhereBeforeKeyValues(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,KEY i(a)); HANDLER t OPEN AS h');
        $this->expectExceptionMessage("Unknown column 'missing' in 'field list'");
        $session->query('HANDLER h READ i = (ROW(1,2)) WHERE missing');
    }

    public function testEarlyKeepsStoredCallsBeforeTheAbsentHandler(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'stored functions in HANDLER ... READ'");
        $session->query('HANDLER h READ i = (ROW(1,2)) WHERE f()');
    }

    public function testPreparePreservesLeftToRightOperandPreparation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); HANDLER t OPEN AS h');
        $this->expectExceptionCode(1241);
        $session->query('HANDLER h READ FIRST WHERE ROW(1,2)+missing');
    }
}
