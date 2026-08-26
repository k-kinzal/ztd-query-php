<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSessionFactory;
use ZtdQuery\Platform\SessionFactory;

#[CoversNothing]
final class SessionFactoryTest extends TestCase
{
    public function testFakeImplementsInterface(): void
    {
        $factory = new FakeSessionFactory();

        self::assertInstanceOf(SessionFactory::class, $factory);
    }
}
