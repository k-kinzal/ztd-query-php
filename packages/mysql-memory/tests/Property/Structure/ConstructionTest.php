<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;

/**
 * Exercises the public semantic constructor domain without generating SQL.
 */
#[CoversNothing]
#[Medium]
final class ConstructionTest extends TestCase
{
    /**
     * A repaired clone operand remains representable in a supported language profile.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testCloneInstanceRetainsQuotedPasswordAndDirectoryOperands(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('823a2230910f3f4d34f8537f3d259098:1');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $statement = $construction->object(\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance::class, 6);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance::class, $statement);
        self::assertNull($statement->password->radix);
        self::assertNull($statement->directory?->radix);
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new \Fuzz\Structure\Profiles())->select($statement)->grammar);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerAssignmentProfiles(): iterable
    {
        yield 'standalone password in a variable list' => [197];
        yield 'legacy expiration with a modern account name' => [982];
    }

    /**
     * Replays whole-model counterexamples through the unchanged type-driven generator.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    #[DataProvider('providerAssignmentProfiles')]
    public function testAssignmentConstructorDomainsHaveALanguageInterpretation(int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('signatures-20261016:' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $node);
        self::assertSame('mysql', (new \Fuzz\Structure\Profiles())->select($node)->grammar->database());
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerOperands(): iterable
    {
        foreach ([NumberLiteral::class, BooleanLiteral::class, NullLiteral::class, Parameter::class] as $class) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $class . ':' . $seed => [$class, (string) $seed];
            }
        }
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     * @param class-string $class
     */
    #[DataProvider('providerOperands')]
    public function testGeneratedOperandExecutesWithoutSqlInput(string $class, string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $literal = $construction->object($class, 4);
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $literal);
        $reply = (new Execution())->run($literal);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(1, $reply->rows);
        self::assertCount(1, $reply->rows[0]);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testSeedReplaysTheSameConstructorChoices(): void
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $first = new Construction(new Choices('replay'), $catalogue, $metadata);
        $second = new Construction(new Choices('replay'), $catalogue, $metadata);

        self::assertEquals($first->object(NumberLiteral::class, 4), $second->object(NumberLiteral::class, 4));
        self::assertSame($first->rejections, $second->rejections);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testRecursiveSetRootFitsTheBudgetWithoutReplacingItsType(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('all-model-roots:111');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());

        self::assertInstanceOf(SetOperation::class, $construction->object(SetOperation::class, 6));
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }
    /**
     * Uses the retained non-empty collection types without replacing the selected root.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testPrivilegeRootFitsTheBudgetWithoutReplacingItsType(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('2674a4091ceacb6852ced919ff7dec51:402');
        $choices->one($catalogue->roots());
        $class = \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class;
        $construction = new Construction($choices, $catalogue, new Metadata());

        self::assertInstanceOf($class, $construction->object($class, 6));
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }
    /**
     * Replays a constructor budget exhaustion with the retained collection types.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testRevokeRolesFitsTheBudgetWithNonemptyCollections(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('ffa5f501db4aaf2f94844bca78c9dc8f:246');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $class = \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles::class;

        self::assertInstanceOf($class, $construction->object($class, 6));
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }

    /**
     * Uses the role-list element type instead of sampling a general privilege.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    public function testGrantRolesFitsTheBudgetWithItsRoleElementType(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('e86863ababc9d6721024d56dcde28f5c:697');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $class = \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles::class;

        self::assertInstanceOf($class, $construction->object($class, 6));
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerGrantSeeds(): iterable
    {
        yield 'first exhausted grant' => ['e86863ababc9d6721024d56dcde28f5c:172'];
        yield 'second exhausted grant' => ['e86863ababc9d6721024d56dcde28f5c:512'];
    }

    /**
     * Constructs the retained grant operands directly, without searching SQL or execution results.
     *
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When a constructor cannot be invoked
     */
    #[DataProvider('providerGrantSeeds')]
    public function testGrantPrivilegesFitsTheBudgetWithItsGranteeType(string $seed): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices($seed);
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $class = \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges::class;

        self::assertInstanceOf($class, $construction->object($class, 6));
        self::assertLessThanOrEqual(2000, $construction->attempts);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     * @throws ReflectionException When constructor types cannot be inspected
     */
    public function testObjectUsesTheAllocatedBudgetForDependentConstructorOperands(): void
    {
        $construction = new Construction(new Choices('source-switch:3'), new Catalogue(), new Metadata());
        $option = $construction->object(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption::class, 4);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption::class, $option);
        self::assertGreaterThan(47, $construction->attempts);
        self::assertLessThanOrEqual(384, $construction->attempts);
    }

}
