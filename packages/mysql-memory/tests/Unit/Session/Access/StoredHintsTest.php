<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Instance;
use MySqlMemory\Session\Access\StoredHints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

#[CoversClass(StoredHints::class)]
#[Small]
final class StoredHintsTest extends TestCase
{
    public function testLoadPublishesConditionsFromTheRetainedDefinition(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->semantics()->analyze('CREATE PROCEDURE p() SELECT /*+ RESOURCE_GROUP(a) */ 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $definition);
        self::assertInstanceOf(CreateProcedure::class, $definition);
        $session->text = 'unrelated invalid input';
        (new StoredHints())->load($definition, $session);
        self::assertSame([3515], array_column($session->diagnostics->conditions, 1));
        self::assertSame('unrelated invalid input', $session->text);
    }
    public function testRetainedPreservesSignalPositionsWhenLaterSqlConsumesTheResourceNotice(): void
    {
        $conditions = [['Warning', 3515, 'resource'], ['Warning', 3127, 'block'], ['Error', 1644, 'signal']];
        $signals = [2 => ['RETURNED_SQLSTATE' => '45000']];
        self::assertSame([$conditions, $signals], (new StoredHints())->retained($conditions, $signals, 1));
        self::assertSame([[['Warning', 3127, 'block'], ['Error', 1644, 'signal']], [1 => ['RETURNED_SQLSTATE' => '45000']]], (new StoredHints())->retained($conditions, $signals, 2));
    }
}
