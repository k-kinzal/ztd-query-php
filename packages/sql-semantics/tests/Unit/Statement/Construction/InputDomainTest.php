<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\InputDomain;
use SqlSemantics\Statement\Construction\ScalarInput;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(InputDomain::class)]
#[Small]
final class InputDomainTest extends TestCase
{
    public function testCheckRejectsAnExternalImplementationBeforeCallingIt(): void
    {
        $external = new class () implements ScalarInput {};
        $this->expectException(InvalidConstruction::class);
        InputDomain::check($external);
    }
}
