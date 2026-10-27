<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel;

#[CoversClass(RefusedGrantLevel::class)]
#[Medium]
final class RefusedGrantLevelTest extends TestCase
{
    public function testMessageDescribesTheTypedTargetContradiction(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
        self::assertInstanceOf(CreateProcedure::class, $create);
        self::assertInstanceOf(DeferredGrantLevel::class, $create->body);
        self::assertSame('The privilege and object kind do not fit the grant level.', $create->body->problem()->message());
    }
}
