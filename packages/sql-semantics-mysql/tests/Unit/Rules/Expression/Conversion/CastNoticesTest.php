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
    public function testCapacityUsesTheResolvedCharacterSetBeforeRuntimeValues(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $target = new CastTarget(CastKind::Char, '2147483648');
        $domain = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(2147483648, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'));
        (new CastNotices())->capacity($target, $domain, $derivation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange::class, $derivation->facts()->diagnostics[0]);
    }

    public function testDeriveRetainsTheTargetNoticeWithoutRequiringAnOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        (new CastNotices())->derive(new CastTarget(CastKind::NationalChar), $derivation);
        self::assertEquals([new Deprecation(Deprecated::National)], $derivation->facts()->warnings);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCharsetRetainsTheUnknownNameAsATypedLookupCondition(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        (new CastNotices())->charset('missing_charset', $derivation);
        self::assertEquals([new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset('missing_charset')], $derivation->facts()->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $derivation->facts()->warnings[0]);
    }
}
