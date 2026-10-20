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
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Large]
final class ProgramHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement|Scalar}>
     */
    public static function providerInputs(): iterable
    {
        $name = new QualifiedName(new Name('routine.name'));
        $nodes = [
            'call' => new M\Dml\ProcedureCall($name, [new M\Literal\NumberLiteral('7')]),
            'alter' => new M\Routine\AlterRoutine(M\Routine\ProgramKind::Function, $name),
            'drop' => new M\Routine\DropProgram(M\Routine\ProgramKind::Function, $name),
            'function' => new M\Call\FunctionCall($name->name, [new M\Call\CallArgument(new M\Literal\NumberLiteral('7'))]),
            'show function' => new M\Utility\Show\Program\ShowCreateFunction($name),
            'show procedure' => new M\Utility\Show\Program\ShowCreateProcedure($name),
        ];
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            foreach ($nodes as $case => $node) {
                yield $release . ' ' . $case => [$release, $node];
            }
        }
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInputs')]
    public function testCompareKeepsTheRequestAcrossAbsentAndCreatedRoutineState(string $release, M\Statement|Scalar $node): void
    {
        $result = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)), port: 29740);
        self::assertTrue($result['matches'], json_encode($result, JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('error', $result['native']);
        self::assertNotEmpty($result['transitions']);
        $last = $result['transitions'][count($result['transitions']) - 1];
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertSame($result['sql'], $last['sql']);
    }
}
