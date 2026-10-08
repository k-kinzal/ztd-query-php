<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(ResourceGroupHint::class)]
#[Small]
final class ResourceGroupHintTest extends TestCase
{
    public function testNameAnswersResourceGroup(): void
    {
        self::assertSame(HintName::ResourceGroup, (new ResourceGroupHint('g'))->name());
    }

    public function testTextQuotesTheGroup(): void
    {
        self::assertSame('RESOURCE_GROUP(`a b`)', (new ResourceGroupHint('a b'))->text());
    }

    public function testAnEmptyGroupIsRefused(): void
    {
        $this->expectExceptionMessage('A resource group has a name.');

        new ResourceGroupHint('');
    }
}
