<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Query;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Query\Notices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDumpfile;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;

#[CoversClass(Notices::class)]
#[Small]
final class NoticesTest extends TestCase
{
    public function testCompletedRetainsTheNoticeFromTheSemanticQueryWithoutSourceText(): void
    {
        $query = new Select([], [new Star()], where: new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), locking: [new LockingClause(LockStrength::ShareMode)], into: new IntoDumpfile(new Text('text')), intoPosition: IntoPosition::AfterQuery);
        $failure = new RejectedInput(new RefusedQuery(new DeferredQuery($query)), 123, false);
        $session = (new Instance('8.4.7'))->connect();
        $session->text = 'unrelated input';
        $notices = (new Notices())->completed($failure, $session);
        self::assertCount(1, $notices);
        self::assertSame(123, $notices[0][0]);
        self::assertSame(3962, $notices[0][1]->code());
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame('unrelated input', $session->text);
    }

    public function testCompletedHasNoQueryNoticeForAnotherRefusal(): void
    {
        $failure = new RejectedInput(new NotSupportedYet('AT LOCAL'), 12);
        self::assertSame([], (new Notices())->completed($failure, (new Instance())->connect()));
    }
}
