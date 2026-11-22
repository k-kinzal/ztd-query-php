<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Call;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Call::class)]
#[Medium]
final class CallTest extends TestCase
{
    public function testDeriveStatementLeavesTheRowOpenOnTheProcedure(): void
    {
        $operation = (new Inspection())->inspect('CALL app.p(1, b => 2)');
        self::assertSame([false, ['the signature of routine p']], [$operation->shape()?->complete(), array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\MissingInput $missing): string => $missing->describe(), $operation->shape()->missing ?? [])]);
    }

    public function testDeriveStatementDerivesTheArguments(): void
    {
        $operation = (new Inspection())->inspect("CALL p('a' || 1)");
        self::assertInstanceOf(Call::class, $operation->statement);
        self::assertTrue($operation->facts->covers($operation->statement->call));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheCall(): void
    {
        self::assertSame('CALL s.p(1, x => 2)', (new Inspection())->inspect('call S.p(1, x => 2)')->toString());
    }
}
