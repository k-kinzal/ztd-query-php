<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Exercises the public semantic constructor domain without generating SQL.
 */
#[CoversNothing]
#[Medium]
final class OperandProperties extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerReports(): iterable
    {
        foreach ([ShowStatus::class, ShowVariables::class] as $class) {
            for ($seed = 0; $seed < 50; $seed++) {
                yield $class . ':' . $seed => [$class, (string) $seed];
            }
        }
    }

    /**
     * @param class-string $class
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerReports')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesReportScopeCannotBecomeASetOnlyScope(string $class, string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $statement = $construction->object($class, 5);
        self::assertTrue($statement instanceof ShowStatus || $statement instanceof ShowVariables);
        self::assertContains($statement->scope, [null, VariableScope::Global, VariableScope::Session]);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneralExpressionsExcludePositionRestrictedOperands(): void
    {
        $scalars = (new Catalogue())->implementations(Scalar::class);

        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable::class, $scalars);
        self::assertNotContains(\SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget::class, $scalars);
    }
}
