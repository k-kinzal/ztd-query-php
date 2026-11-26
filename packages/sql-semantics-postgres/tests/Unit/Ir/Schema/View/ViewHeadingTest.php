<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewHeading;

#[CoversClass(ViewHeading::class)]
#[Small]
final class ViewHeadingTest extends TestCase
{
    public function testCheckAcceptsCaseSensitiveDistinctNames(): void
    {
        $columns = [new Identifier('n'), new Identifier('N')];
        (new ViewHeading())->check($columns, 2);

        self::assertSame(['n', 'N'], array_column($columns, 'value'));
    }
}
