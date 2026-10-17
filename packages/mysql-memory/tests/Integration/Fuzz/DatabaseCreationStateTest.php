<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class DatabaseCreationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testQualifiedRoutineKeepsItsInitialFailureAndSucceedsAfterSchemaCreation(GrammarRelease $release): void
    {
        $name = new QualifiedName(new Name('f'), new Name("schema'with`quotes"));
        $body = new ReturnStatement(new NumberLiteral('12345'));
        $node = new CreateFunction($name, new ParameterList([]), new Integral(IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation($body, [new Determinism(true)]));
        $observation = (new Comparison())->compare($node, new LanguageProfile($release), '');

        self::assertTrue($observation['matches'], serialize($observation));
        self::assertSame([1049, '42000', "Unknown database 'schema'with`quotes'"], $observation['native']['error']);
        self::assertCount(3, $observation['transitions']);
        self::assertArrayNotHasKey('error', $observation['transitions'][2]['native']);
        self::assertArrayNotHasKey('error', $observation['transitions'][2]['memory']);
        self::assertSame($observation['sql'], $observation['transitions'][2]['sql']);
        self::assertSame($body, $node->body);
    }
}
