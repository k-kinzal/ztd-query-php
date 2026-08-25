<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\HydratorInterface;
use stdClass;

#[CoversNothing]
final class HydratorInterfaceTest extends TestCase
{
    public function testHydrateAnswersTheObjectTheImplementationBuilt(): void
    {
        $entity = new stdClass();
        $hydrator = self::createStub(HydratorInterface::class);
        $hydrator->method('hydrate')->willReturn($entity);

        self::assertSame($entity, $hydrator->hydrate(['id' => 1], stdClass::class));
    }
}
