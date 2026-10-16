<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\SyntaxPrefixes;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ZeroKeyPrefix;

#[CoversClass(SyntaxPrefixes::class)]
#[Small]
final class SyntaxPrefixesTest extends TestCase
{
    public function testCompletedKeepsLaterInputConditions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tree = $semantics->parser()->parse('CREATE TABLE t(a TEXT, KEY(a(0)))');
        $lowering = new Lowering(\SqlSemantics\Contract\Platforms::of('mysql')->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $notice = (new SyntaxPrefixes())->completed($tree->find('key_part')[0], $lowering);
        self::assertFalse($notice?->aborts);
        self::assertInstanceOf(ZeroKeyPrefix::class, $notice->problem);
        self::assertNull((new SyntaxPrefixes())->completed($tree, $lowering));
    }

    public function testRejectedRefusesEvenAnUnreachableKeyDefinition(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN IF FALSE THEN CREATE TABLE t(a TEXT, KEY(a(000))); END IF; END');
            self::fail('A stored definition cannot defer the prefix domain.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(ZeroKeyPrefix::class, $failure->problem);
            self::assertSame('a', $failure->problem->column->value);
            self::assertFalse($failure->aborts);
        }
    }
}
