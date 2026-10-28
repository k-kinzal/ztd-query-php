<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Partition\Method;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HashMethod values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod>
 */
final class HashMethodGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $linear Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $expression Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $linear, ErisGenerator $expression, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod {
            return new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(linear: $parts[0], expression: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $linear,
            $expression,
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates CREATE and ALTER requests whose partition method reads session state.
     * The complete table, method, expression and counts are composed before execution.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function refusedRequests(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $requests = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Statement {
            [$name, $kind, $linear, $create, $variable, $count] = $parts;
            $table = new \SqlSemantics\Statement\Identifier\QualifiedName($name);
            $expression = $variable ? new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('value_' . $count)) : new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []);
            $method = match ($kind) {
                1 => new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, $expression),
                2 => new \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod(\SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::List, $expression),
                default => new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod($linear, $expression),
            };
            $partitions = $kind === 3
                ? new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod(false, null, []), null, new \SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning($method, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral((string) $count)), [])
                : new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause($method, null, null, []);
            if (!$create) {
                return new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable($table, [new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy($partitions)]);
            }
            $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('id')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            return new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($table, [$column], partitioning: $partitions);
        }, \Tests\Generator\Operands::tuple(
            new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(),
            Generators::choose(0, 3),
            Generators::bool(),
            Generators::bool(),
            Generators::bool(),
            Generators::choose(1, 8)
        ));
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests);
    }

}
