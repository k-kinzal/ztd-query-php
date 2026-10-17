<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Program-only DO wildcards retain their declared meaning without input SQL.
 */
#[CoversNothing]
#[Medium]
final class DeferredEvaluationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerSeeds(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * @throws JsonException When the semantic inventory is malformed
     * @throws ReflectionException When constructor metadata cannot be read
     */
    #[DataProvider('providerSeeds')]
    public function testGeneratedProgramMemberDefersItsConditionUntilInvocation(string $release, int $seed): void
    {
        $body = (new Construction(new Choices('deferred-evaluation:' . $seed), new Catalogue(), new Metadata()))->object(DeferredEvaluation::class, 2);
        self::assertInstanceOf(DeferredEvaluation::class, $body);
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $name = new QualifiedName(new Name('p'));

        self::assertInstanceOf(Completion::class, $execution->runIn($session, new CreateProcedure($name, new ParameterList([]), $body)));
        $failure = null;
        try {
            $execution->runIn($session, new ProcedureCall($name));
        } catch (SqlError $error) {
            $failure = $error;
        }

        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame($body->problem()->message(), $failure->getMessage());
        self::assertSame('unrelated invalid input', $session->text);
    }
}
