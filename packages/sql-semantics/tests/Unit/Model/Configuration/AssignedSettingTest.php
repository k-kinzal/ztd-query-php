<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Configuration\AssignedSetting::class)]
final class AssignedSettingTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Configuration\AssignedSetting(['work_mem'], \SqlSemantics\Model\Configuration\SettingScope::Session, new Node('setting', 0, []), []);
    }
}
