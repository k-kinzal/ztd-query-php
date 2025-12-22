<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeErrorClassifier;
use ZtdQuery\Platform\ErrorClassifier;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ErrorClassifierTest extends TestCase
{
    public function testFakeImplementsInterface(): void
    {
        $classifier = new FakeErrorClassifier();

        self::assertInstanceOf(ErrorClassifier::class, $classifier);
    }
}
