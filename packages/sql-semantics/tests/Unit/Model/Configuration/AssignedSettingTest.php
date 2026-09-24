<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(\SqlSemantics\Model\Configuration\AssignedSetting::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AssignedSettingTest extends TestCase
{
    public function testRejectsMissingRequiredOperands(): void
    {
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Configuration\AssignedSetting(['work_mem'], \SqlSemantics\Model\Configuration\SettingScope::Session, new Node('setting', 0, []), []);
    }

    public function testValuesRejectAnEmptyAssignment(): void
    {
        $source = new Node('set', 0, []);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Configuration\AssignedSetting(['x'], \SqlSemantics\Model\Configuration\SettingScope::Session, $source, []);
    }

    public function testValuesKeepTheNameScopeAndExpressions(): void
    {
        $source = new Node('set', 0, []);
        $value = \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql);
        $setting = new \SqlSemantics\Model\Configuration\AssignedSetting(['x'], \SqlSemantics\Model\Configuration\SettingScope::Session, $source, [$value]);
        self::assertSame([['x'], \SqlSemantics\Model\Configuration\SettingScope::Session, [$value]], [$setting->name, $setting->scope, $setting->values]);
    }
}
