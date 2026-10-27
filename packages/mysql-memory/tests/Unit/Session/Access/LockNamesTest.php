<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\LockNames;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(LockNames::class)]
#[Medium]
final class LockNamesTest extends TestCase
{
    public function testFailureRetainsTheNumberOfModesBeforeAnIdentifierRefusal(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $node = new LockTables([new TableLock(new QualifiedName(new Name(str_repeat('a', 65))), null, LockMode::LowPriorityWrite)]);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($session->semantics()->context(), $node, new \SqlSemantics\Statement\Source\SourceMap());
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
        $statement1 = (new Embedding())->statement($node);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $result = $result1;
        self::assertSame(1046, ($result instanceof SqlError ? [$result->getCode(), $result->sqlState(), $result->getMessage()] : null)[0] ?? null);
        self::assertSame(['Warning', 'Error'], array_column($warnings1, 0));
        self::assertSame(['1287', '1046'], array_column($warnings1, 1));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
