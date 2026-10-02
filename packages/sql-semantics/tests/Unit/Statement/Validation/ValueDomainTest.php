<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Transaction\Rollback;
use SqlSemantics\Statement\Validation\ValueDomain;

#[CoversClass(ValueDomain::class)]
#[Small]
final class ValueDomainTest extends TestCase
{
    public function testContainsRequiresConcreteRegistrationNotAnOperationInterface(): void
    {
        $foreign = new class () implements Operation {
            /** @throws LogicException */
            public function toString(): string
            {
                throw new LogicException('An unregistered implementation must not be invoked.');
            }
        };
        self::assertFalse(ValueDomain::contains($foreign));
        self::assertTrue(ValueDomain::contains(new Rollback()));
    }
}
