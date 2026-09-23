<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Configuration\ResetSetting::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ResetSettingTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Configuration\ResetSetting([], \SqlSemantics\Model\Configuration\SettingScope::Session, new Node('setting', 0, []));
    }
}
