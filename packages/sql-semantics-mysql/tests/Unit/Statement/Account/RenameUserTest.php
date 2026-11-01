<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;

#[CoversClass(RenameUser::class)]
#[Medium]
final class RenameUserTest extends TestCase
{
    public function testDeriveStatementPublishesTheConcreteRequest(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('RENAME USER a TO b');
        self::assertInstanceOf(RenameUser::class, $statement);
        self::assertNull($statement->problem);
        self::assertSame([], $statement->declarations());
        self::assertNotNull($statement->source);
    }

    public function testRenderWritesThePairs(): void
    {
        self::assertSame('RENAME USER \'a\'@\'h\' TO b, c TO d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("rename user 'a'@'h' to b, c to d")->toString());
    }

    public function testToStringReconstructsAContradictoryRequest(): void
    {
        self::assertSame('RENAME USER a TO a, b TO c', (new Semantics(Dialect::MySql))->analyze('rename user a to a, b to c')->toString());
    }

    public function testProfileRetainsTheSelectedLegacyGrammar(): void
    {
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql5651, (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('rename user a to b')->profile()->grammar);
    }

    public function testDeclarationsDoesNotDeclareTheNamedAccountsAsTables(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('RENAME USER a TO b')->declarations());
    }
    public function testDeriveStatementRetainsContradictoryCurrentIdentityPairs(): void
    {
        $current = new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser();
        $statement = new RenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming($current, new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        self::assertNotNull($statement->problem);
        self::assertSame($statement->renamings, $statement->problem->renamings);
        self::assertNull($statement->source);
        self::assertSame('RENAME USER CURRENT_USER TO CURRENT_USER', $statement->toString());
    }

    public function testDeriveProgramKeepsAnUnreachedRename(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r TO r');
        self::assertInstanceOf(MySqlStatement::class, $operation);
        self::assertSame([], $operation->diagnostics());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(RenameUser::class, $operation->body);
    }

    public function testRenderPreservesTheCompleteRename(): void
    {
        self::assertSame('CREATE PROCEDURE p() RENAME USER r@h TO r@h', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r@h TO r@h')->toString());
    }

    public function testProblemKeepsItsOriginalRequest(): void
    {
        $request = new RenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        self::assertNotNull($request->problem);
        self::assertSame($request->renamings, $request->problem->renamings);
    }

    public function testRenderRetainsDuplicateAccountsInAStoredRename(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER r TO r, a TO b');
        self::assertInstanceOf(MySqlStatement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(RenameUser::class, $operation->body);
        self::assertCount(2, $operation->body->renamings);
        self::assertNotNull($operation->body->problem);
        self::assertSame('RENAME USER requires consistent source and destination identities throughout its ordered pairs.', $operation->body->problem->message());
        self::assertSame('CREATE PROCEDURE p() RENAME USER r TO r, a TO b', $operation->toString());
    }

    public function testRenderKeepsConsistentPairsInTheSameStatementType(): void
    {
        $request = new RenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))]);
        self::assertNull($request->problem);
        self::assertSame('RENAME USER CURRENT_USER TO r', $request->toString());
    }
}
