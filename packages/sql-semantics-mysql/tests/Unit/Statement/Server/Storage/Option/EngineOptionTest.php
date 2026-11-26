<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(EngineOption::class)]
#[Medium]
final class EngineOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame('DROP TABLESPACE ts ENGINE `ndb`', (new Inspection())->inspect('drop tablespace ts storage engine = ndb')->toString());
    }

    public function testKeywordNamesTheOption(): void
    {
        self::assertSame('ENGINE', (new EngineOption(new Name('ndb')))->keyword());
    }
}
