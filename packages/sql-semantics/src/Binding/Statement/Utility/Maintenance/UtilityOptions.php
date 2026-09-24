<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Maintenance;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\OptionWords;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads a parenthesized PostgreSQL utility option list the way the server's option readers see it.
 * @visibility SqlSemantics
 */
final class UtilityOptions
{
    /**
     * Returns each option name, folded as an identifier, with its decoded argument; a later repetition replaces an earlier one.
     * @return array<string, array{string|int|null, Node}>
     * @throws UnclassifiedSql
     */
    public static function read(Node $source, Identifiers $identifiers): array
    {
        $options = [];
        foreach (Tree::outer($source, ['utility_option_elem']) as $option) {
            $name = Tree::child($option, ['utility_option_name']) ?? throw new UnclassifiedSql('A utility option requires its name.');
            $token = $name->tokens()[0] ?? throw new UnclassifiedSql('A utility option requires its name.');
            $key = match (strtoupper($token->text)) {
                'ANALYZE', 'ANALYSE' => 'analyze',
                'FORMAT' => 'format',
                default => $identifiers->name($token),
            };
            $argument = Tree::child($option, ['utility_option_arg']);
            unset($options[$key]);
            $options[$key] = [$argument === null || $argument->tokens() === [] ? null : OptionWords::raw($argument, $identifiers), $option];
        }
        return $options;
    }

    /**
     * Reads a Boolean argument: no argument, true, on, 1 are true and false, off, 0 are false, in any case.
     * @param array{string|int|null, Node} $option
     * @throws InvalidSql
     */
    public static function boolean(array $option): bool
    {
        return $option[0] === null ? true : (OptionWords::boolean($option[0]) ?? throw new InvalidSql(InputViolation::MaintenanceOption, $option[1]));
    }

    /**
     * Reads a required integer argument written as an integer constant.
     * @param array{string|int|null, Node} $option
     * @throws InvalidSql
     */
    public static function integer(array $option): int
    {
        return is_int($option[0]) ? $option[0] : throw new InvalidSql(InputViolation::MaintenanceOption, $option[1]);
    }

    /**
     * Reads a required buffer ring size.
     * @param array{string|int|null, Node} $option
     * @throws InvalidSql
     */
    public static function bufferUsageLimit(array $option): BufferUsageLimit
    {
        try {
            return new BufferUsageLimit((string) ($option[0] ?? throw new InvalidSql(InputViolation::MaintenanceOption, $option[1])));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::MaintenanceOption, $option[1], $error);
        }
    }
}
