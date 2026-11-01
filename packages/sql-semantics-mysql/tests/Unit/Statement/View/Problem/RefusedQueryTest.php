<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedQuery::class)]
#[Small]
final class RefusedQueryTest extends TestCase
{
    public function testMessageRetainsTheTargetCommandAndCompleteReadBody(): void
    {
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery(new DeferredQuery(new Select([], [new Star()])));
        $name = new QualifiedName(new Name('v'));
        $problem = new RefusedQuery($name, true, $body);

        self::assertSame('No tables used', $problem->message());
        self::assertSame($body, $problem->query);
        self::assertSame($name, $problem->view);
        self::assertTrue($problem->alter);
    }
}
