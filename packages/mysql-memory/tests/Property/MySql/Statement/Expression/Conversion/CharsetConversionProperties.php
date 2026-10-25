<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Expression\Conversion;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CharsetConversionProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCharacterSetSeeds(): iterable
    {
        for ($seed = 0; $seed < 50; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * Deriving a directly constructed conversion reports its unknown character set.
     *
     * @param string $seed The reproducible construction choices
     */
    #[DataProvider('providerCharacterSetSeeds')]
    #[Test]
    public function satisfiesDirectConstructionReportsUnknownCharsetWithoutAnInternalFailure(string $seed): void
    {
        $choices = new Choices('charset-structure:' . $seed);
        $name = $choices->one(['', 'absent_' . $choices->integer(1, 10000)]);
        $operand = $choices->one([new NumberLiteral((string) $choices->integer(0, 100)), new ColumnUse(new Name('missing'))]);
        $expression = new Model($operand, new CharsetName(new Name($name)));
        $release = $choices->one(['8.0.44', '8.4.7', '9.1.0']);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: '" . $name . "'");
        $session1 = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement($expression);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
    }

    /**
     * Unsized casts remain expression types while their operands determine the result size.
     */
    #[DataProvider('providerCharacterSetSeeds')]
    #[Test]
    public function satisfiesUnsizedStringCastsExecuteFromConstructedSemanticCatalogue(string $seed): void
    {
        $choices = new Choices('unsized-string:' . $seed);
        $value = 'value_' . $choices->integer(0, 100000);
        $kind = $choices->one([CastKind::Binary, CastKind::Char, CastKind::NationalChar]);
        $cast = new Cast(new StringLiteral([$value]), new CastTarget($kind));
        $expression = new Model($cast, new CharsetName(new Name($choices->one(['latin1', 'utf8mb4', 'binary']))));
        $release = $choices->one(['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0']);
        $session2 = (new Instance($release))->connect();
        $statement2 = (new Embedding())->statement($expression);
        $session2->activity->begin();
        StatementCounters::received($session2);
        $session2->hinted = [];
        $session2->dots = [];
        $operation2 = (new Binding())->operation($session2, $statement2, [], new LanguageProfile($session2->semantics()->profile()->grammar, $session2->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session2))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$value]], $reply->rows);
    }
}
