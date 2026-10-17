<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedColumns::class)]
#[Small]
final class RefusedColumnsTest extends TestCase
{
    public function testMessageKeepsTheViewIdentityAndRefusedName(): void
    {
        $view = new QualifiedName(new Name('v'), new Name('d'));
        $problem = new RefusedColumns($view, new Name('a '));
        self::assertSame($view, $problem->view);
        self::assertSame("Incorrect column name 'a '", $problem->message());
    }
}
