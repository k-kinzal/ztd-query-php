<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Retyped::class)]
#[Small]
final class RetypedTest extends TestCase
{
    public function testDomainAnswersTheResolvedTypeInsteadOfTheCompiledOne(): void
    {
        $domain = Domain::integer()->withNullable(false);

        self::assertSame($domain, (new Retyped(new ColumnRead(Domain::integer(), 0), $domain))->domain());
    }

    public function testEvaluateEvaluatesTheCompiledExpression(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [5, 6]);

        self::assertSame(6, (new Retyped(new ColumnRead(Domain::integer(), 1), Domain::double()))->evaluate($frame));
    }
}
