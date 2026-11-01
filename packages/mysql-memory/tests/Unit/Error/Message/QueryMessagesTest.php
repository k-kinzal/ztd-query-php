<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Message\QueryMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryMessages::class)]
#[Small]
final class QueryMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (QueryError $error): string => $error->name, QueryError::cases());
        self::assertSame($cases, array_keys(QueryMessages::DEFINITIONS));
    }
}
