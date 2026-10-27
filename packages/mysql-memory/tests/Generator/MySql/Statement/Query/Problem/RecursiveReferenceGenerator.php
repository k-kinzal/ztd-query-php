<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RecursiveReference values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference>
 */
final class RecursiveReferenceGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $table Generated constructor operand
     */
    public function __construct(ErisGenerator $table)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference {
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference(table: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $table
        ));
    }
    /**
     * Generates the typed refusal of a self-reference with no nonrecursive anchor.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery>}>
     */
    public static function executionForms(): iterable
    {
        $queries = Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference $missing): \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery {
            $name = new \SqlSemantics\Statement\Identifier\QualifiedName($missing->table);
            $output = new \SqlSemantics\Statement\Fact\QueryFact([new \SqlSemantics\Statement\Shape\OpenStar([$missing])], \SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive);
            $body = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($name), queryInformation: $output);
            $definition = new \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression($missing->table, [], $body);
            $read = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($name));
            $query = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\MySql\Statement\Query\With\With(true, [$definition]), $read);
            return new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery($query));
        }, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()));
        yield 'Missing recursive CTE anchor' => [\SqlSemantics\Contract\GrammarRelease::MySql847, $queries];
    }

}
