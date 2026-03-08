<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyRequest;

#[CoversClass(FamilyRequest::class)]
final class FamilyRequestTest extends TestCase
{
    public function testConstructsReadonlyFamilyRequest(): void
    {
        $request = new FamilyRequest('family.id', ['arity' => 3]);

        self::assertSame('family.id', $request->familyId);
        self::assertSame(['arity' => 3], $request->parameters);
    }
}
