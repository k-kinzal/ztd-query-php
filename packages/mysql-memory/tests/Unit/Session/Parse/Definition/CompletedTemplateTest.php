<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Parse\Definition\CompletedTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompletedTemplate::class)]
#[Small]
final class CompletedTemplateTest extends TestCase
{
    public function testRetainsTheExactCompletion(): void
    {
        $reply = new Completion(warnings: 1);
        self::assertSame($reply, (new CompletedTemplate($reply))->reply);
    }
}
