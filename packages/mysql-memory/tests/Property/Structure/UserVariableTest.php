<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Exercises numeric variable names through constructed expressions in one session.
 */
#[CoversNothing]
#[Medium]
final class UserVariableTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPasswordAssignments(): iterable
    {
        for ($seed = 0; $seed < 20; $seed++) {
            $choices = new Choices('password-assignment:' . $seed);
            yield (string) $seed => [$choices->one(['', 'secret', "a'b", '日本語', '\\']), (string) $choices->integer(0, 1000)];
        }
    }

    /**
     * A legacy password item and an ordinary assignment execute from constructed operands.
     */
    #[DataProvider('providerPasswordAssignments')]
    public function testConstructedPasswordItemsExecuteWithVariableAssignments(string $password, string $value): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $execution = new Execution();
        $statement = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment(new \SqlSemantics\Platform\MySql\Statement\Account\Password(new \SqlSemantics\Platform\MySql\Statement\Literal\Text($password), \SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::Password)),
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment(new UserVariable(new Name('value')), new NumberLiteral($value)),
        ]);
        $write = $execution->runIn($session, $statement);
        $read = $execution->runIn($session, new UserVariable(new Name('value')));

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $write);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([[$value]], $read->rows);
        self::assertSame($password, $session->instance->accounts->find(new \MySqlMemory\Account\Identity('root', '%'))?->password);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['0', '1', '-1', '9223372036854775807', '9223372036854775808', '001', '1.5'] as $name) {
                yield $release . ':' . $name => [$release, $name];
            }
        }
    }

    /**
     * Numeric names remain SQL strings even when PHP stores their map keys as integers.
     */
    #[DataProvider('providerNames')]
    public function testConstructedAssignmentsAndReadsKeepNumericNames(string $release, string $name): void
    {
        $value = (string) (new Choices($release . ':' . $name))->integer(0, 1000);
        $session = (new Instance($release))->connect();
        $execution = new Execution();
        $write = $execution->runIn($session, new VariableAssignment(new UserVariable(new Name($name)), new NumberLiteral($value)));
        $read = $execution->runIn($session, new UserVariable(new Name($name)));

        self::assertInstanceOf(ResultSet::class, $write);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([[$value]], $write->rows);
        self::assertSame($write->rows, $read->rows);
    }
}
