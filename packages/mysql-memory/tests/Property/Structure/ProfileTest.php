<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Profiles;
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

/**
 * Chooses interpretation from model correspondence without consulting execution.
 */
#[CoversNothing]
#[Medium]
final class ProfileTest extends TestCase
{
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
