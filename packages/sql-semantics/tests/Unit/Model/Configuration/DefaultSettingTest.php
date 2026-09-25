<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Configuration\DefaultSetting::class)]
#[Medium]
final class DefaultSettingTest extends TestCase
{
    public function testRetainsTheSettingTargetAndDefaultOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET LOCAL work_mem TO DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        $setting = $statement->settings[0];
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\DefaultSetting::class, $setting);
        self::assertSame(['work_mem'], $setting->name);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Local, $setting->scope);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingAction::Default, $setting->action);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
