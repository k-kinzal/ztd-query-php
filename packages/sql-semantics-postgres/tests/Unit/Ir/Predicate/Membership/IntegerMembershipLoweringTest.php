<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Membership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(IntegerMembershipLowering::class)]
#[Small]
final class IntegerMembershipLoweringTest extends TestCase
{
    public function testLowerRetainsSignedWidthsAndAlternativeOrder(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 NOT IN (NULL, CAST(NULL AS bigint), 2147483648)');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(InList::class, $operation->statement->where);

        $value = (new IntegerMembershipLowering())->lower(
            $operation->statement->where,
            new SourceBinding($operation->binding(), null),
            true
        );

        self::assertTrue($value->negated);
        self::assertInstanceOf(IntegerLiteral::class, $value->subject);
        self::assertInstanceOf(NullLiteral::class, $value->alternatives[0]);
        self::assertInstanceOf(WideIntegerNull::class, $value->alternatives[1]);
        self::assertInstanceOf(WideIntegerLiteral::class, $value->alternatives[2]);
    }
}
