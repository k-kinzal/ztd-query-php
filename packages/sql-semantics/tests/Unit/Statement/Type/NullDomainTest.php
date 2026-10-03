<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(NullDomain::class)]
#[Small]
final class NullDomainTest extends TestCase
{
    public function testNullHasAnExplicitTypeOutcomeWithoutADeclaration(): void
    {
        self::assertSame(NullDomain::Null, (new NullConstant())->type());
    }
}
