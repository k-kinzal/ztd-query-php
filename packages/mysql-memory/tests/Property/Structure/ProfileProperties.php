<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use JsonException;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;
use Tests\Generator\Profiles;

/**
 * Chooses interpretation from model correspondence without consulting execution.
 */
#[CoversNothing]
#[Medium]
final class ProfileProperties extends TestCase
{
    /**
     * Preserves the ALTER root and choices while its own invariant rejects a column-only timestamp clause.
     *
     * @throws JsonException When a semantic inventory is invalid
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesTheGeneratedAlterAfterRejectingAColumnTimestampClause(): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('semantic-types-1611:23');
        $choices->one($catalogue->roots());
        $construction = new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata());
        $node = $construction->object(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, 6);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        self::assertArrayHasKey(\SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting::class . ': ALTER COLUMN SET DEFAULT accepts a literal or a parenthesized expression; a bare timestamp clause belongs to a column definition.', $construction->rejections);
    }

    /**
     * Preserves the root and random stream of the incompatible RESET PERSIST counterexample.
     *
     * @throws JsonException When a semantic inventory is invalid
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesTheGeneratedProcedureAfterRejectingIncompatibleReleaseOperands(): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('semantic-types-1607:1099');
        $choices->one($catalogue->roots());
        $construction = new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata());
        $node = $construction->object(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        self::assertArrayHasKey(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class . ': A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.', $construction->rejections);
    }

    /**
     * Language-dependent limits select a corresponding profile without discarding the object.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectRetainsAccountNamesThatNeedLaterLanguageLimits(): void
    {
        $profiles = new Profiles();
        $legacy = new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 17)))]);
        $modern = new \SqlSemantics\Platform\MySql\Statement\Account\DropUser(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u'), new Name(str_repeat('h', 61)))]);

        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql5744, $profiles->select($legacy)->grammar);
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, $profiles->select($modern)->grammar);
    }

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
     */
    #[DataProvider('providerRepairedModels')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectFindsAProfileForTheConstructedStatement(string $seed, int $index): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices($seed . ':' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object($class, 6);
        self::assertTrue($node instanceof \SqlSemantics\Platform\MySql\Statement\Statement || $node instanceof \SqlSemantics\Statement\Scalar);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * Replays a family-selected trial whose modern hints contradicted its legacy column list.
     *
     * @throws JsonException When a semantic inventory is invalid
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesAConstructedInsertWithHints(): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('partition-20261015:300');
        $choices->one($catalogue->roots());
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $node);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function providerCompositionModels(): iterable
    {
        yield 'delete comma lists' => [\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, 302];
        yield 'replica position vocabulary' => [\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica::class, 601];
    }

    /**
     * Replays the selected root type even when a new invariant rejects an intermediate candidate.
     *
     * @param class-string $class The root selected by the original whole-model campaign
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerCompositionModels')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesConstructedListComposition(string $class, int $index): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('entry-20261015:' . $index);
        $choices->one($catalogue->roots());
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object($class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $node);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function providerIntervalAndUpgradeModels(): iterable
    {
        yield 'legacy database upgrade in a modern request' => [\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree::class, 483];
        yield 'unmarked row in a leading interval' => [\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat::class, 519];
    }

    /**
     * Constructor rejection excludes impossible syntax composition before profile selection.
     *
     * @param class-string $class The selected root type
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerIntervalAndUpgradeModels')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesIntervalAndUpgradeComposition(string $class, int $index): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('alter-20261015:' . $index);
        $choices->one($catalogue->roots());
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object($class, 6);
        self::assertTrue($node instanceof \SqlSemantics\Platform\MySql\Statement\Statement || $node instanceof \SqlSemantics\Statement\Scalar);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerTimeCampaignModels(): iterable
    {
        yield 'legacy parameter with vector type' => [369];
        yield 'hexadecimal key prefix' => [429];
        yield 'legacy alter with engine attributes' => [513];
        yield 'default collation with transactional create' => [675];
        yield 'query destination inside scalar subquery' => [829];
    }

    /**
     * Every accepted constructor result retains a language interpretation after retrying rejected combinations.
     *
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerTimeCampaignModels')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesConstructorDomainsFromTheTimeCampaign(int $index): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('time-20261015:' . $index);
        $choices->one($roots);
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object($roots[$index % count($roots)], 6);
        self::assertTrue($node instanceof \SqlSemantics\Platform\MySql\Statement\Statement || $node instanceof \SqlSemantics\Statement\Scalar);
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectUsesLexicalRequirementsBeforeExecution(string $left, string $right): void
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectRetainsNamedParameters(): void
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectDoesNotRequireResolvableNamesOrSuccessfulExecution(): void
    {
        $node = new Select([], [new SelectExpression(new ColumnUse(new Name('missing')))]);
        $profile = (new Profiles())->select($node);

        self::assertSame('mysql', $profile->grammar->database());
        self::assertSame(ParameterStyle::Native, $profile->parameters);
    }

    /**
     * Keeps the selected EXPLAIN root when incompatible DELETE aliases are rejected.
     *
     * @throws JsonException When a semantic inventory is invalid
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesSelectPreservesExplainAfterRejectingMixedDeleteGenerations(): void
    {
        $catalogue = new \Tests\Generator\Catalogue();
        $choices = new Choices('row-roles-20261017:187');
        $choices->one($catalogue->roots());
        $node = (new \Tests\Generator\Construction($choices, $catalogue, new \Tests\Generator\Metadata()))->object(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, 6);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $node);

        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
    }

}
