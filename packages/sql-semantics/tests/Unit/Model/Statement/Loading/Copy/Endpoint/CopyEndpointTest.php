<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyClient;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyEndpoint;

#[CoversClass(CopyEndpoint::class)]
final class CopyEndpointTest extends TestCase
{
    public function testClientIsAnEndpoint(): void
    {
        self::assertSame([CopyEndpoint::class], array_values(class_implements(new CopyClient())));
    }
}
