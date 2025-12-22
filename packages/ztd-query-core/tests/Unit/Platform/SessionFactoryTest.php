<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSessionFactory;
use ZtdQuery\Platform\SessionFactory;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SessionFactoryTest extends TestCase
{
    public function testFakeImplementsInterface(): void
    {
        $factory = new FakeSessionFactory();

        self::assertInstanceOf(SessionFactory::class, $factory);
    }
}
