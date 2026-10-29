<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;

/**
 * Executes generated session-variable dependencies without deriving their bindings again.
 */
#[CoversNothing]
#[Large]
final class UserVariableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Reads the value established by the generated preceding assignment.
     */
    #[Test]
    public function satisfiesGeneratedAssignmentAndReadShareTheSameVariable(): void
    {
        $this->forAll(UserVariableGenerator::assignmentSequence())->then(static function (array $sequence): void {
            [$write, $read] = $sequence;
            self::assertInstanceOf(SetVariables::class, $write);
            self::assertInstanceOf(QueryRoot::class, $read);
            $assignment = $write->items[0];
            self::assertInstanceOf(UserAssignment::class, $assignment);
            self::assertInstanceOf(NumberLiteral::class, $assignment->value);
            $binding = $read->field(0)->resolution;
            self::assertInstanceOf(UserVariableBinding::class, $binding);
            self::assertSame($assignment->variable->name, $binding->name);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated variable meaning without source SQL';
            $execution = new Execution($session);
            $execution->statement($write);
            $reply = $execution->statement($read);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[$assignment->value->text]], $reply->rows);
            self::assertSame($read->field(0)->name?->value, $reply->columns[0]->name);
        });
    }

    /**
     * An unspecified variable domain does not prevent reading an absent value.
     */
    #[Test]
    public function satisfiesMissingSessionStateIsResolvedByExecution(): void
    {
        $this->forAll(UserVariableGenerator::missingState())->then(static function (QueryRoot $read): void {
            $type = $read->field(0)->type;
            self::assertInstanceOf(Dependent::class, $type);
            self::assertInstanceOf(SessionState::class, $type->missing[0]);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated missing session state without source SQL';
            $reply = (new Execution($session))->statement($read);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[null]], $reply->rows);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * A generated system catalog supplies the read's name, scope and result domain.
     */
    #[Test]
    public function satisfiesCatalogReadsCarryTheirDeclaredDomain(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Variable\Catalog\SystemVariablesGenerator::readQueries())->then(/** @param array{\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables, QueryRoot} $input */
            static function (array $input): void {
                [$catalog, $statement] = $input;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::class, $catalog);
                self::assertInstanceOf(QueryRoot::class, $statement);
                $entry = array_values($catalog->definitions)[0];
                $session = (new Instance('8.4.7'))->connect();
                $session->text = 'Generated catalog read without SQL input';
                $reply = (new Execution($session))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                self::assertCount(1, $reply->rows);
                self::assertCount(1, $reply->rows[0]);
                self::assertCount(1, $reply->columns);
                self::assertSame($entry->domain->field->value, $reply->columns[0]->type->value);
                self::assertSame($statement->field(0)->name?->value, $reply->columns[0]->name);
                self::assertSame('Generated catalog read without SQL input', $session->text);
            }
        );
    }

    /**
     * A bare word reaches system assignment with either known or program-dependent facts.
     */
    #[Test]
    public function satisfiesGeneratedBareWordsAssignTheSelectedBoolean(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Utility\Set\BareNameGenerator::booleanAssignments())->then(static function (SetVariables $statement): void {
            $assignment = $statement->items[0];
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment::class, $assignment);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName::class, $assignment->value);
            $session = (new Instance('8.4.7'))->connect();
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
            self::assertSame($assignment->value->word->value, $session->variables->read('autocommit'));
            self::assertSame($assignment->value->word->value === 'ON', $session->transaction->autocommit());
            self::assertSame([], $session->diagnostics->conditions);
        });
    }

}
