<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption;

#[CoversClass(EngineAttributeOption::class)]
#[Medium]
final class EngineAttributeOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("CREATE TABLESPACE ts ENGINE_ATTRIBUTE '{}'", (new Semantics(Dialect::MySql))->analyze("create tablespace ts engine_attribute = '{}'")->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('ENGINE_ATTRIBUTE', (new EngineAttributeOption(new Text('{}')))->keyword());
    }
}
