<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Large]
final class StructureOutcomeTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, Statement|Scalar, bool}>
     */
    public static function providerOutcomes(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            yield $release->value . ': number' => [$release, new NumberLiteral('1'), false];
            yield $release->value . ': null' => [$release, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), false];
            yield $release->value . ': extract' => [$release, new \SqlSemantics\Platform\MySql\Statement\Call\Extract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Microsecond, new NumberLiteral('0')), false];
            yield $release->value . ': binary collation' => [$release, new StringLiteral(['abc']), false];
            yield $release->value . ': parameter' => [$release, new Parameter(), false];
            yield $release->value . ': condition' => [$release, new Signal(new SqlState(new Text('45000'))), true];
            if ($release !== GrammarRelease::MySql5651) {
                yield $release->value . ': disconnect' => [$release, new \SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown(), false];
            }
        }
        yield 'retained window needs a database' => [GrammarRelease::MySql847, new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate(new GroupConcat([new NumberLiteral('1')]), new WindowSpec()))])), true];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerOutcomes')]
    public function testCompareChecksSuccessfulAndRejectedDirectStructures(GrammarRelease $release, Statement|Scalar $node, bool $error): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile($release));

        self::assertTrue($observation['matches'], json_encode($observation, JSON_THROW_ON_ERROR));
        self::assertSame($error, isset($observation['memory']['error']));
        self::assertSame($error, isset($observation['native']['error']));
    }
}
