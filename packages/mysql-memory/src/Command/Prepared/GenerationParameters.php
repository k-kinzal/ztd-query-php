<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Captures MySQL 5.7 generation parameters as values fixed at table creation.
 * Marker positions come from semantic occurrences, independently of input SQL.
 * Observed through PREPARE, EXECUTE, SHOW CREATE TABLE and subsequent inserts.
 *
 * @visibility MySqlMemory
 */
final class GenerationParameters
{
    /**
     * Returns the written marker occurrences without retaining rendered SQL.
     *
     * @return list<Node>
     */
    public function occurrences(Node $definition, GrammarRelease $release): array
    {
        $output = new Output(new Codec($release));
        $definition->render($output);
        return $output->parameters();
    }

    /**
     * Supplies a preparation slot for each marker in a complete table instruction.
     */
    public function capture(Node $definition, GrammarRelease $release): ParameterBindings
    {
        return new ParameterBindings(array_fill(0, count($this->occurrences($definition, $release)), [Domain::string(16383, M\Type\Resolved\Collation::binary()), false]));
    }

    /**
     * Freezes execution values by occurrence, retaining the reusable template.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters Bound protocol values
     * @return array<int, Scalar> Literal values indexed by original occurrence identity
     */
    public function values(Node $definition, GrammarRelease $release, array $parameters): array
    {
        $values = [];
        foreach ($this->occurrences($definition, $release) as $position => $occurrence) {
            \SqlSemantics\Diagnostic\Check::invariant(isset($parameters[$position]), 'A retained generation marker has a bound execution value.');
            $values[spl_object_id($occurrence)] = $this->literal(...$parameters[$position]);
        }
        return $values;
    }

    /**
     * Preserves NULL, numeric values and decoded character bytes as semantic operands.
     */
    public function literal(int|float|string|null $value, Domain $domain): Scalar
    {
        if ($value === null) {
            return new M\Literal\NullLiteral();
        }
        if (!in_array($domain->kind, [M\Type\Resolved\Kind::Integer, M\Type\Resolved\Kind::Decimal, M\Type\Resolved\Kind::Double], true)) {
            return new M\Literal\StringLiteral([(string) $value], new \SqlSemantics\Statement\Identifier\Name($domain->collation->charset->name));
        }
        $text = (string) $value;
        $number = new M\Literal\NumberLiteral(ltrim($text, '-+'));
        return str_starts_with($text, '-') ? new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, $number) : $number;
    }
}
