<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlDeleteTargets;

#[CoversClass(MySqlDeleteTargets::class)]
#[UsesClass(DeleteTransformer::class)]
final class MySqlDeleteTargetsTest extends TestCase
{
    public function testOfAnswersTheTableASingleTableDeleteRemovesRowsFrom(): void
    {
        $statement = (new Parser('DELETE FROM users WHERE id = 1'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertSame(['name' => 'users', 'alias' => 'users'], (new MySqlDeleteTargets())->of($statement));
    }

    public function testOfAnswersTheTableAMultiTableDeleteNamesBeforeFrom(): void
    {
        $statement = (new Parser('DELETE u FROM users AS u JOIN orders AS o ON o.user_id = u.id'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertSame(['name' => 'users', 'alias' => 'u'], (new MySqlDeleteTargets())->of($statement));
    }

    public function testFirstNamedAliasAnswersNothingWhereNoNameIsWrittenBeforeFrom(): void
    {
        $statement = (new Parser('DELETE FROM users'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertNull((new MySqlDeleteTargets())->firstNamedAlias($statement));
    }

    public function testNamedAliasesAnswersEveryNameWrittenBeforeFrom(): void
    {
        $statement = (new Parser('DELETE u, o FROM users AS u JOIN orders AS o ON o.user_id = u.id'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertSame(['u', 'o'], (new MySqlDeleteTargets())->namedAliases($statement));
    }

    public function testFirstRelationRefusesAStatementReadingFromSomethingNamingNoTable(): void
    {
        $statement = new DeleteStatement();
        $statement->from = [new \PhpMyAdmin\SqlParser\Components\Expression()];

        $this->expectException(RuntimeException::class);

        (new MySqlDeleteTargets())->firstRelation($statement);
    }

    public function testFirstRelationAnswersNothingKnownWhereTheStatementReadsFromNothing(): void
    {
        $statement = new DeleteStatement();

        self::assertSame(['name' => 'unknown', 'alias' => 'unknown'], (new MySqlDeleteTargets())->firstRelation($statement));
    }

    public function testTableNamedAnswersTheTableAUsingClauseGivesTheNameTo(): void
    {
        $statement = (new Parser('DELETE u FROM users AS u USING orders AS o'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertSame(['users', null], [
            (new MySqlDeleteTargets())->tableNamed($statement, 'u'),
            (new MySqlDeleteTargets())->tableNamed($statement, 'nothing'),
        ]);
    }

    public function testRelationsAnswersEverythingTheStatementReadsFrom(): void
    {
        $statement = (new Parser('DELETE u FROM users AS u JOIN orders AS o ON o.user_id = u.id'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);

        self::assertCount(2, (new MySqlDeleteTargets())->relations($statement));
    }
}
