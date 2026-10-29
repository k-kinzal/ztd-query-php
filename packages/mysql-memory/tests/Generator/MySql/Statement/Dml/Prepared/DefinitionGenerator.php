<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Prepared;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes Definition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
 */
final class DefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation> $initial Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $database Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative>> $alternatives Generated constructor operand
     */
    public function __construct(ErisGenerator $initial, ErisGenerator $database, ErisGenerator $alternatives)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition(initial: $parts[0], database: $parts[1], alternatives: $parts[2]);
        }, Generators::tuple(
            $initial,
            $database,
            $alternatives
        ));
    }

    /**
     * Composes a mode-independent statement across the interpretation domain.
     * Its varying statement operands remain upstream of every alternative.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative> $initials Initial interpretations of ASCII statements
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
     */
    public static function portable(ErisGenerator $initials): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative $initial): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            $language = $initial->interpretation->language;
            $alternatives = [];
            foreach (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation::all($language->grammar, $language->parameters) as $interpretation) {
                $alternatives[] = $interpretation->key() === $initial->interpretation->key() ? $initial : new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($interpretation, $initial->statement, $initial->sources);
            }
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($initial->interpretation, new \SqlSemantics\Statement\Identifier\Name('pbt'), $alternatives);
        }, $initials);
    }

    /**
     * Composes a preparation whose escaped literal stops being grammatical when backslashes are disabled.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative> $initials Initial interpretations containing an escaped single quote
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition>
     */
    public static function changingEscapes(ErisGenerator $initials): ErisGenerator
    {
        $refusals = new SyntaxRefusalGenerator(Generators::choose(1, 128), Generators::choose(1, 4), Generators::elements(["quoted'", "value'", "text'"]));
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition {
            [$initial, $refusal] = $parts;
            $alternatives = [];
            foreach (\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation::all($initial->interpretation->language->grammar, $initial->interpretation->language->parameters) as $interpretation) {
                $alternatives[] = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($interpretation, $interpretation->language->lexical->noBackslashEscapes ? $refusal : $initial->statement, $initial->sources);
            }
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($initial->interpretation, new \SqlSemantics\Statement\Identifier\Name('pbt'), $alternatives);
        }, Generators::tuple($initials, $refusals));
    }


    /**
     * Generates a prepared table read and the metadata change that invalidates it.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition, \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function tableReads(\SqlSemantics\Contract\GrammarRelease $release, bool $refused): ErisGenerator
    {
        $queries = Generators::map(static fn (\SqlSemantics\Statement\Identifier\Name $name): \SqlSemantics\Platform\MySql\Statement\QueryRoot => new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select(
            [],
            [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral([$refused ? "value'" : 'value']))],
            new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName($name)),
        )), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator());
        $interpretations = new InterpretationGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant($release)), new \Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator(Generators::constant('utf8mb4'), Generators::constant(4)));
        $initials = new AlternativeGenerator($interpretations, $queries);
        return Generators::map(self::tableSequence(...), $refused ? self::changingEscapes($initials) : self::portable($initials));
    }

    /**
     * Builds the table change and bound read belonging to a generated preparation.
     *
     * @return array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition, \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable, \SqlSemantics\Platform\MySql\Statement\QueryRoot}
     * @throws LogicException When the generated definition is not a table read.
     */
    public static function tableSequence(\SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition $definition): array
    {
        $initial = $definition->select($definition->initial);
        if (!$initial->statement instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot || !$initial->statement->query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select || !$initial->statement->query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference) {
            throw new LogicException('The generated preparation reads one named table.');
        }
        $name = $initial->statement->query->from->name;
        $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('original')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        $added = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('added')), $column->specification);
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $definition->initial->language->grammar->value, searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
        $create = \SqlSemantics\Facade\Semantics::fromStatement($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($name, [$column]));
        $context = $semantics->context([$create]);
        $alter = \SqlSemantics\Facade\Semantics::fromStatement($context, new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable($name, [new \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn($added)]));
        $query = \SqlSemantics\Facade\Semantics::fromStatement($context, $initial->statement);
        if (!$create instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || !$alter instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable || !$query instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot) {
            throw new LogicException('Publication preserves the generated preparation prerequisites.');
        }
        return [$create, $definition, $alter, $query];
    }
}
