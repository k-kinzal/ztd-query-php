<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser;

#[CoversClass(DeferredRenameUser::class)]
#[Medium]
final class DeferredRenameUserTest extends TestCase
{
    public function testDeriveProgramKeepsAnUnreachedRename(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r TO r');
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredRenameUser::class, $operation->statement->body);
    }

    public function testRenderPreservesTheCompleteRename(): void
    {
        self::assertSame('CREATE PROCEDURE p() RENAME USER r@h TO r@h', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r@h TO r@h')->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $request = new DeferredRenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        self::assertSame($request, $request->problem()->request);
    }

    public function testRenderRetainsDuplicateAccountsInAStoredRename(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r TO r, a TO b');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredRenameUser::class, $operation->statement->body);
        self::assertCount(2, $operation->statement->body->renamings);
        self::assertSame('RENAME USER requires consistent source and destination identities throughout its ordered pairs.', $operation->statement->body->problem()->message());
        self::assertSame('CREATE PROCEDURE p() RENAME USER r TO r, a TO b', $operation->toString());
    }

    public function testRenderRequiresADeferredRename(): void
    {
        $this->expectExceptionMessage('A deferred account rename retains contradictory identity requirements.');
        new DeferredRenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))]);
    }
}
