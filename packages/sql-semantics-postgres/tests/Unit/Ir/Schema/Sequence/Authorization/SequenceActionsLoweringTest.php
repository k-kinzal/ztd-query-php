<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceActionsLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceActionsLowering::class)]
final class SequenceActionsLoweringTest extends TestCase
{
    public function testLowerRoutesEachCompleteActionFamily(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket', 'CREATE ROLE report_reader']);

        self::assertInstanceOf(SetSequenceLogging::class, $semantics->analyze('ALTER SEQUENCE ticket SET UNLOGGED', $context));
        $value = $semantics->analyze('ALTER SEQUENCE ticket SET LOGGED, OWNER TO report_reader, SET UNLOGGED', $context);
        self::assertInstanceOf(AlterSequenceAuthorization::class, $value);
        self::assertSame('ALTER SEQUENCE "public"."ticket" SET LOGGED, OWNER TO "report_reader", SET UNLOGGED', $value->toString());
    }
}
