<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(CastNotices::class)]
#[Medium]
final class CastNoticesTest extends TestCase
{
    public function testDeriveRetainsTheTargetNoticeWithoutRequiringAnOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        (new CastNotices())->derive(new CastTarget(CastKind::NationalChar), $derivation);
        self::assertEquals([new Deprecation(Deprecated::National)], $derivation->facts()->warnings);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
