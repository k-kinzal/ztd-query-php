<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\InstanceAction;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InstanceAction::class)]
#[Medium]
final class InstanceActionTest extends TestCase
{
    public function testActionIsTheActionOfAlterInstance(): void
    {
        $alter = (new Inspection())->inspect('ALTER INSTANCE RELOAD TLS');
        self::assertInstanceOf(AlterInstance::class, $alter->statement);

        self::assertInstanceOf(ReloadTls::class, $alter->statement->action);
    }
}
