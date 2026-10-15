<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Profiles;
use JsonException;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Chooses interpretation from model correspondence without consulting execution.
 */
#[CoversNothing]
#[Medium]
final class ProfileTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerRepairedModels(): iterable
    {
        yield 'condition operand' => ['e91a273aff1ba87d6d5a79bfff108f6d', 153];
        yield 'create table options' => ['e91a273aff1ba87d6d5a79bfff108f6d', 298];
        yield 'late subquery limit' => ['6a7d1bc45f092c61dc9b56b4d0676cd1', 121];
        yield 'relay log position' => ['6a7d1bc45f092c61dc9b56b4d0676cd1', 609];
        yield 'explain modern options' => ['0a00fd08a150854e056cde648d20c202', 129];
        yield 'replication option list' => ['0a00fd08a150854e056cde648d20c202', 605];
    }

    /**
     * Replays type-driven construction at formerly incompatible model inputs.
     *
     * @throws JsonException When a semantic inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    #[DataProvider('providerRepairedModels')]
    public function testSelectFindsAProfileForTheConstructedStatement(string $seed, int $index): void
    {
        $catalogue = new \Fuzz\Structure\Catalogue();
        $choices = new Choices($seed . ':' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new \Fuzz\Structure\Construction($choices, $catalogue, new \Fuzz\Structure\Metadata()))->object($class, 6);
        self::assertTrue($node instanceof \SqlSemantics\Platform\MySql\Statement\Statement || $node instanceof \SqlSemantics\Statement\Scalar);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * Replays a family-selected trial whose modern hints contradicted its legacy column list.
     *
     * @throws JsonException When a semantic inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testSelectPreservesAConstructedInsertWithHints(): void
    {
        $catalogue = new \Fuzz\Structure\Catalogue();
        $choices = new Choices('partition-20261015:300');
        $choices->one($catalogue->roots());
        $node = (new \Fuzz\Structure\Construction($choices, $catalogue, new \Fuzz\Structure\Metadata()))->object(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $node);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOperands(): iterable
    {
        for ($seed = 0; $seed < 20; $seed++) {
            $choices = new Choices('profiles:' . $seed);
            yield (string) $seed => [$choices->one(['a', '0', "'", '\\', 'あ']), $choices->one(['b', '1', "'", '\\', 'い'])];
        }
    }

    /**
     * Concatenation determines the lexical mode for each generated pair of values.
     */
    #[DataProvider('providerOperands')]
    public function testSelectUsesLexicalRequirementsBeforeExecution(string $left, string $right): void
    {
        $node = new Concatenation(new StringLiteral([$left]), new StringLiteral([$right]));
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        $reply = (new Execution())->run($node, substr($profile->grammar->value, 6), mode: $profiles->mode($profile));

        self::assertTrue($profile->lexical->pipesAsConcat);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$left . $right]], $reply->rows);
    }

    /**
     * Named marker interpretation survives profile selection and direct execution.
     */
    public function testSelectRetainsNamedParameters(): void
    {
        $node = new Parameter(':input');
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        $reply = (new Execution())->run($node, substr($profile->grammar->value, 6), [[7, Domain::integer()]], $profiles->mode($profile));

        self::assertSame(ParameterStyle::Named, $profile->parameters);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['7']], $reply->rows);
    }

    /**
     * A missing column does not cause the selector to discard the structure.
     */
    public function testSelectDoesNotRequireResolvableNamesOrSuccessfulExecution(): void
    {
        $node = new Select([], [new SelectExpression(new ColumnUse(new Name('missing')))]);
        $profile = (new Profiles())->select($node);

        self::assertSame('mysql', $profile->grammar->database());
        self::assertSame(ParameterStyle::Native, $profile->parameters);
    }
}
