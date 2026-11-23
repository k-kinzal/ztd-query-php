<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewCheckOption;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewOptionsLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ViewOptionsLowering::class)]
#[Medium]
final class ViewOptionsLoweringTest extends TestCase
{
    public function testLowerResolvesNamedValuesAndTheirPresence(): void
    {
        $statement = (new Inspection())->inspect(
            'CREATE VIEW report WITH (security_barrier=off, security_invoker=TRUE, check_option=local) AS SELECT 8 AS n',
        )->statement;
        self::assertInstanceOf(CreateView::class, $statement);
        self::assertSame([false, true, ViewCheckOption::Local], (new ViewOptionsLowering())->lower($statement));
    }

    public function testFlagDistinguishesUnspecifiedFromFalse(): void
    {
        $options = new ViewOptionsLowering();

        self::assertNull($options->flag([], 'security_invoker'));
        self::assertFalse($options->flag(['security_invoker' => 'false'], 'security_invoker'));
    }

    public function testCheckResolvesLocalAndCascaded(): void
    {
        $options = new ViewOptionsLowering();

        self::assertSame(ViewCheckOption::Local, $options->check(['check_option' => 'local']));
        self::assertSame(ViewCheckOption::Cascaded, $options->check(['check_option' => 'cascaded']));
    }

    public function testWordDecodesTheNativeToggle(): void
    {
        self::assertSame('true', (new ViewOptionsLowering())->word(Toggle::True));
    }
}
