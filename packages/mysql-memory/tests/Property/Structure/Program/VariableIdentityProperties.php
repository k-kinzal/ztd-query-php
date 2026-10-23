<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Generated variable identities and observable spellings execute without source SQL.
 */
#[CoversNothing]
#[Medium]
final class VariableIdentityProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 30; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Exercises independently spelled references to generated parameter declarations.
     */
    #[DataProvider('providerProfiles')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesEquivalentNamesResolveAndRetainTheirResultSpelling(string $release, int $seed): void
    {
        $choices = new Choices('variable-identity:' . $seed);
        $parameters = $items = $arguments = $names = $targets = $writes = $written = [];
        $count = $choices->integer(1, 8);
        $profile = new LanguageProfile(GrammarRelease::from('mysql-' . $release));
        for ($position = 0; $position < $count; $position++) {
            [$declared, $read] = $choices->one([['É', 'e'], ['ñ', 'N'], ['Ü', 'u'], ['À', 'a'], ['ç', 'C']]);
            $parameters[] = new Parameter(new Name($declared . $position), new Integral(IntegralKind::Int));
            $names[] = $quoted = '`' . $read . $position . '`';
            $items[] = new SelectExpression(new ColumnUse(new Name($read . $position)), layout: new Layout([new Spelled('', $quoted)]), spellingProfile: $profile);
            $arguments[] = new NumberLiteral((string) $choices->integer(0, 100000));
            $targets[] = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable(new Name($read . $position));
            $written[] = $value = (string) $choices->integer(0, 100000);
            $writes[] = new SelectExpression(new NumberLiteral($value));
        }
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid source';
        $execution = new Execution();
        $name = new QualifiedName(new Name('p'));
        $assign = new QueryRoot(new Select([], $writes, into: new \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables($targets), intoPosition: \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems));
        $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block([], [$assign, new QueryRoot(new Select([], $items))]);
        $execution->runIn($session, new CreateProcedure($name, new ParameterList($parameters), $body));

        $called = $execution->runIn($session, new ProcedureCall($name, $arguments));

        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([$written], $called->replies[0]->rows);
        self::assertSame($names, array_map(static fn (ResultColumn $column): string => $column->name, $called->replies[0]->columns));
    }
}
