<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\KeyTerms::class)]
#[Medium]
final class KeyTermsTest extends TestCase
{
    public function testCheckedRefusesAnEmptyList(): void
    {
        $this->expectExceptionMessage('There is at least one key term.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Command\KeyTerms())->checked([]);
    }
}
