<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Precision::class)]
#[Small]
final class PrecisionTest extends TestCase
{
    public function testDomainReadsResolvedTypesAndNull(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new Precision())->domain(new Known($domain)));
        self::assertEquals(Domain::null(), (new Precision())->domain(new NullOnly()));
        self::assertNull((new Precision())->domain(new Known(new Integral(IntegralKind::Int))));
    }

    public function testAllNeedsEveryOperandResolved(): void
    {
        $domain = Domain::integer();

        self::assertSame([$domain, $domain], (new Precision())->all([new Known($domain), new Known($domain)]));
        self::assertNull((new Precision())->all([new Known($domain), new Known(new Integral(IntegralKind::Int))]));
    }
}
