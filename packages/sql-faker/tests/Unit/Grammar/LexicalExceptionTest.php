<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalException;

#[CoversClass(LexicalException::class)]
final class LexicalExceptionTest extends TestCase
{
    public function testIsALogicException(): void
    {
        self::assertInstanceOf(LogicException::class, new LexicalException('failure'));
    }
}
