<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Utility;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Valid SET roles compose and execute without an input spelling or parser tree.
 */
#[CoversNothing]
#[Medium]
final class SetItemsTest extends TestCase
{
    /**
     * @throws JsonException When the semantic inventory cannot be loaded
     */
    public function testSetItemInventoryContainsOnlyAcceptedAssignmentRoles(): void
    {
        self::assertEqualsCanonicalizing([
            NameAssignment::class, PasswordAssignment::class, SetCharacterSet::class,
            SetNames::class, SystemAssignment::class, UserAssignment::class,
        ], (new Catalogue())->implementations(SetItem::class));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Composed SET items update their distinct connection and result settings.
     */
    #[DataProvider('providerProfiles')]
    public function testConstructedCharacterSetItemsUpdateTheirDeclaredSessionState(string $release, int $seed): void
    {
        $choices = new Choices('set-character-set:' . $seed);
        $first = $choices->one(['latin1', 'utf8mb4', 'binary']);
        $second = $choices->one(['latin1', 'utf8mb4', 'binary']);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new SetVariables([
            new SetNames(new CharsetName(new Name($first))),
            new SetCharacterSet(new CharsetName(new Name($second))),
        ]);

        self::assertInstanceOf(Completion::class, (new Execution())->runIn($session, $statement));
        self::assertSame($second, $session->variables->read('character_set_client'));
        self::assertSame($second, $session->variables->read('character_set_results'));
        self::assertSame($session->variables->read('character_set_database'), $session->variables->read('character_set_connection'));
    }

    /**
     * Local variable identity does not depend on the rejected unquoted input form.
     */
    #[DataProvider('providerProfiles')]
    public function testConstructedLocalNamesAssignmentsRemainExecutable(string $release, int $seed): void
    {
        $choices = new Choices('set-local-names:' . $seed);
        $name = new Name($choices->one(['names', 'NAMES', 'namés']));
        $value = (string) $choices->integer(0, 100000);
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $procedure = new QualifiedName(new Name('p'));
        $body = new Block([], [
            new SetVariables([new NameAssignment($name, new NumberLiteral($value))]),
            new QueryRoot(new Select([], [new SelectExpression(new ColumnUse($name))])),
        ]);
        $execution->runIn($session, new CreateProcedure($procedure, new ParameterList([new Parameter($name, new Integral(IntegralKind::Int))]), $body));

        $reply = $execution->runIn($session, new ProcedureCall($procedure, [new NumberLiteral('0')]));

        self::assertInstanceOf(Batch::class, $reply);
        self::assertInstanceOf(ResultSet::class, $reply->replies[0]);
        self::assertSame([[$value]], $reply->replies[0]->rows);
    }
}
