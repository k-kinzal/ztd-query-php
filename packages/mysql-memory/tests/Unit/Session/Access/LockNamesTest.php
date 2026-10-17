<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use Fuzz\Structure\Audit\Memory;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\LockNames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(LockNames::class)]
#[Medium]
final class LockNamesTest extends TestCase
{
    public function testFailureRetainsTheNumberOfModesBeforeAnIdentifierRefusal(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $node = new LockTables([new TableLock(new QualifiedName(new Name(str_repeat('a', 65))), null, LockMode::LowPriorityWrite)]);
        $operation = new \SqlSemantics\Statement\Operation($session->semantics()->context(), $node);
        $failure = (new LockNames())->failure($operation, $session);

        self::assertNotNull($failure);
        self::assertSame(1, $failure[0]);
        self::assertSame(1059, $failure[1]->getCode());
    }

    public function testCheckPreservesOnlyTheSemanticWarningsReachedBeforeTheDatabaseError(): void
    {
        $qualified = new QualifiedName(new Name('t'), new Name('d'));
        $node = new LockTables([new TableLock($qualified, new Name('a'), LockMode::LowPriorityWrite), new TableLock(new QualifiedName(new Name('t')), new Name('b'), LockMode::Read), new TableLock($qualified, new Name('c'), LockMode::LowPriorityWrite)]);
        $session = (new Instance('5.7.44'))->connect();
        $session->text = 'unrelated invalid input';
        $result = (new Memory())->observe($session, $node);

        self::assertSame(1046, $result['error'][0] ?? null);
        self::assertNotNull($result['warnings']);
        self::assertSame(['Warning', 'Error'], array_column($result['warnings'], 0));
        self::assertSame(['1287', '1046'], array_column($result['warnings'], 1));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
