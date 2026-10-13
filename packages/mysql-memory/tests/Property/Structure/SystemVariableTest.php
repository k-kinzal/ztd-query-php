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
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A directly constructed assignment and read use distinct semantic roles.
 */
#[CoversNothing]
#[Medium]
final class SystemVariableTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * @throws \MySqlMemory\Error\SqlError When the semantic structure cannot execute
     */
    #[DataProvider('providerSeeds')]
    public function testAssignmentAndReadPreserveTheirScopes(string $seed): void
    {
        $choices = new Choices('system-variable:' . $seed);
        $scope = $choices->one([null, ...VariableScope::cases()]);
        $value = $choices->integer(0, 1);
        $session = (new Instance())->connect();
        $execution = new Execution();
        $execution->runIn($session, new SetVariables([new SystemAssignment(new SystemTarget(new Name('autocommit'), $scope), new NumberLiteral((string) $value))]));
        $global = in_array($scope, [VariableScope::Global, VariableScope::Persist, VariableScope::PersistOnly], true);
        $result = $execution->runIn($session, new SystemVariable(new Name('autocommit'), $global ? VariableScope::Global : VariableScope::Session));

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[(string) ($scope === VariableScope::PersistOnly ? 1 : $value)]], $result->rows);
    }
}
