<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SourceOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption>
 */
final class SourceOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral|\SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds|\SqlSemantics\Platform\MySql\Statement\Name\AccountName|\SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck|\SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids|null> $value Generated constructor operand
     * @param ErisGenerator<bool>|null $synonym Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ErisGenerator $kind, ErisGenerator $value, ?ErisGenerator $synonym = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(terminology: $parts[0], kind: $parts[1], value: $parts[2], synonym: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple(
            $terminology,
            $kind,
            $value,
            $synonym ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes complete options whose values violate a server constraint.
     *
     * @return non-empty-list<ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption, int}>>
     */
    public static function conditions(\SqlSemantics\Contract\GrammarRelease $release): array
    {
        $vocabulary = Generators::constant((new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($release));
        $choices = [self::condition(new self($vocabulary, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Delay), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(2147483648, 2147493648)))), 1729)];
        if ($release !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $choices[] = self::condition(new self($vocabulary, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Password), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $length): string => str_repeat('p', $length), Generators::choose(33, 64)))), 3056);
        }
        if (!(new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->legacy($release)) {
            $choices[] = self::condition(new self($vocabulary, Generators::constant(\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::AssignGtidsToAnonymousTransactions), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::map(static fn (int $value): string => 'invalid-' . $value, Generators::choose(0, 1000)))), 1525);
            foreach ([\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RequireRowFormat, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::ConnectionAutoFailover, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::GtidOnly] as $kind) {
                $choices[] = self::condition(new self($vocabulary, Generators::constant($kind), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(2, 1000)))), $kind === \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::RequireRowFormat ? 3945 : 1064);
            }
        }
        return $choices;
    }

    /**
     * Keeps the expected condition attached while an option's operands shrink.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption> $option The composed option
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption, int}>
     */
    public static function condition(ErisGenerator $option, int $code): ErisGenerator
    {
        return Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption $value): array => [$value, $code], $option);
    }

}
