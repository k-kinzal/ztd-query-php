<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Json\Predicate;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Predicate::class)]
#[Small]
final class PredicateTest extends TestCase
{
    public function testDomainIsThatOfThePredicate(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new Predicate(new Constant($domain, 1)))->domain());
    }

    public function testEvaluateEvaluatesThePredicate(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(0, (new Predicate(new Constant(Domain::integer(), 0)))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }
}
