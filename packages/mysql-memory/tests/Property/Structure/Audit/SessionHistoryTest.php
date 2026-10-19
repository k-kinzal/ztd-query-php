<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit;

use Fuzz\Structure\Audit\SessionHistory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Session fixtures retain the identity of the tested references and explain no unrelated error.
 */
#[CoversNothing]
#[Small]
final class SessionHistoryTest extends TestCase
{
    /**
     * Setup statements refer to the same identifier and XA key as the original object.
     */
    public function testFixtureStepsPreserveNamesAndTransactionIdentity(): void
    {
        $name = new Name('state');
        $prepared = (new SessionHistory())->steps(new M\Dml\Prepared\Deallocate($name), 1243);
        self::assertInstanceOf(M\Dml\Prepared\Prepare::class, $prepared[0]);
        self::assertSame($name, $prepared[0]->name);
        $xid = new M\Server\Transaction\Xa\Xid(new M\Literal\Text('id'));
        $xa = (new SessionHistory())->steps(new M\Server\Transaction\Xa\XaCommit($xid), 1397);
        self::assertCount(3, $xa);
        self::assertSame([$xid, $xid, $xid], array_column($xa, 'xid'));
    }

    /**
     * Only the specific missing-state condition for a supported root supplies setup.
     */
    public function testUnrelatedFailuresHaveNoAutomaticStateExplanation(): void
    {
        $node = new M\Dml\Prepared\Deallocate(new Name('state'));
        self::assertSame([], (new SessionHistory())->steps($node, 1064));
        self::assertSame([], (new SessionHistory())->steps($node, 1397));
        self::assertSame([], (new SessionHistory())->steps(new M\Literal\NullLiteral(), 1243));
    }
}
