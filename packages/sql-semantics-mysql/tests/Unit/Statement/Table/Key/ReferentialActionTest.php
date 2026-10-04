<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction;

#[CoversClass(ReferentialAction::class)]
#[Small]
final class ReferentialActionTest extends TestCase
{
    public function testRenderWritesTheEventAndTheAction(): void
    {
        $action = new ReferentialAction(ReferenceEvent::Update, ReferenceOption::SetNull);

        self::assertSame([ReferenceEvent::Update, ReferenceOption::SetNull], [$action->event, $action->option]);
    }
}
