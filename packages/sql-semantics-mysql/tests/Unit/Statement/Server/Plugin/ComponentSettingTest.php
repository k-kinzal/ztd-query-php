<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting;

#[CoversClass(ComponentSetting::class)]
#[Medium]
final class ComponentSettingTest extends TestCase
{
    public function testRenderWritesScopeVariableAndValue(): void
    {
        self::assertSame("INSTALL COMPONENT 'c' SET GLOBAL sql_mode = 'x'", (new Semantics(Dialect::MySql))->analyze("install component 'c' set global sql_mode = 'x'")->toString());
    }

    public function testStandaloneNamesHaveWordMeaningBeforePlanning(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("INSTALL COMPONENT 'c' SET GLOBAL sql_mode=((word)), max_connections=word+1");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent::class, $operation);
        $word = $operation->settings[0]->value;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName::class, $word);
        self::assertSame('word', $word->word->value);
        $type = $word->scalarInformation()->type;
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $type);
        self::assertSame('VARCHAR', $type->descriptor->name());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic::class, $operation->settings[1]->value);
    }
}
