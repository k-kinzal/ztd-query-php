<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSchemaReflector;
use ZtdQuery\Platform\SchemaReflector;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SchemaReflectorTest extends TestCase
{
    public function testFakeImplementsInterface(): void
    {
        $reflector = new FakeSchemaReflector();

        self::assertInstanceOf(SchemaReflector::class, $reflector);
    }
}
