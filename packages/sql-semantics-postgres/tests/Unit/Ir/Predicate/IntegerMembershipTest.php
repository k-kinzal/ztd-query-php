<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;

#[CoversClass(IntegerMembership::class)]
#[Small]
final class IntegerMembershipTest extends TestCase
{
    public function testAlternativesKeepRepeatsAndDetachCallerReferences(): void
    {
        $subject = new IntegerLiteral('2');
        $wide = new WideIntegerLiteral('2147483648');
        $null = new NullLiteral();
        $referenced = $wide;
        $value = new IntegerMembership($subject, [&$referenced, $null, $wide], true);
        $referenced = new WideIntegerLiteral('2147483649');

        self::assertSame($subject, $value->subject);
        self::assertSame([$wide, $null, $wide], $value->alternatives);
        self::assertTrue($value->negated);
    }
}
