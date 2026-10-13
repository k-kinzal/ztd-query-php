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
