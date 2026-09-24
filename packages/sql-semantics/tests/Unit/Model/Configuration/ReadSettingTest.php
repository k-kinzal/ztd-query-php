<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Configuration\ReadSetting;
use SqlSemantics\Model\Configuration\SettingAction;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ReadSetting::class)]
final class ReadSettingTest extends TestCase
{
    public function testReadsANamedSettingWithoutOperands(): void
    {
        $source = new Node('setting', 0, []);
        $setting = new ReadSetting(['main', 'cache_size'], SettingScope::Database, $source);
        self::assertSame(SettingAction::Read, $setting->action);
        self::assertSame(['main', 'cache_size'], $setting->name);
        self::assertSame(SettingScope::Database, $setting->scope);
        self::assertSame($source, $setting->source);
    }

    /**
     * @param list<string> $name
     */
    #[TestWith([[]])]
    #[TestWith([['']])]
    #[TestWith([['main', '']])]
    public function testRejectsEmptyNameParts(array $name): void
    {
        $this->expectException(InvalidStructure::class);
        new ReadSetting($name, SettingScope::Database, new Node('setting', 0, []));
    }
}
