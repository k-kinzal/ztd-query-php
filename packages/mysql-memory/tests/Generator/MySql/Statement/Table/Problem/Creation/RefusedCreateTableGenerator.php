<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Problem\Creation;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable;
use Tests\Generator\MySql\Statement\Routine\Program\Definition\DeferredCreateTableGenerator;
use Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator;

/**
 * Generates RefusedCreateTable values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedCreateTable>
 */
final class RefusedCreateTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedCreateTable> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedCreateTable from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedCreateTable => new RefusedCreateTable(definition: $parts[0], problem: $parts[1]),
            Generators::tuple(
                new DeferredCreateTableGenerator($release, $depth - 1),
                new DiagnosticGenerator($release, $depth - 1)
            )
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RefusedCreateTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedCreateTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
