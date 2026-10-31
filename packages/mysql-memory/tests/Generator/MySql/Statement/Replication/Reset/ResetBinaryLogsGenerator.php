<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * Generates ResetBinaryLogs values from the generators of its operands.
 *
 * @implements ErisGenerator<ResetBinaryLogs>
 */
final class ResetBinaryLogsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ResetBinaryLogs> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ResetBinaryLogs from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $releases = new Releases();
        $terminology = $releases->binaryLogs($release);
        $this->delegate = Generators::map(
            static fn (array $parts): ResetBinaryLogs => new ResetBinaryLogs(terminology: $terminology, first: $parts[0], synonym: $terminology === Terminology::Current && ((new ReleaseDomain())->bit($release) & 48) !== 0 && $parts[1], inputSource: null),
            Generators::tuple(
                $releases->legacy($release) ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $index): Numeral => new Numeral((string) $index), Generators::choose(1, 2000000000))),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ResetBinaryLogs $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<ResetBinaryLogs>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ResetBinaryLogs>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
