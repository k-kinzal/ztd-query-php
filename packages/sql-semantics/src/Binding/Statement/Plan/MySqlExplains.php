<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Plan;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Binds MySQL `EXPLAIN FOR CONNECTION id` and `EXPLAIN FOR DATABASE name statement`.
 * @visibility SqlSemantics
 */
final class MySqlExplains
{
    /**
     * Binds the running connection or the database-scoped statement an EXPLAIN FOR names.
     * @throws InvalidSql
     */
    public static function target(Origin $origin, Node $command, MySqlPlan $options, QueryContext $context): BoundStatement
    {
        $schema = Tree::child($command, ['opt_explain_for_schema']);
        try {
            if ($schema !== null) {
                $tokens = $schema->tokens();
                $database = MySqlNames::read($tokens[count($tokens) - 1], $context->tables->identifiers);
                $statement = Tree::child($command, ['select_stmt', 'insert_stmt', 'replace_stmt', 'update_stmt', 'delete_stmt']) ?? Tree::invalid($command, 'explained statement');
                $tables = $context->tables;
                $scoped = new QueryContext(new TableResolver($tables->schema, $tables->identifiers, $database, $tables->diagnostics), $context->ids);
                return new ExplainInDatabaseStatement($origin, $database, (new StatementBinder($scoped->tables))->node($statement, $statement, $scoped), $options);
            }
            if ($options->analyze || $options->variable !== null) {
                throw new InvalidSql(InputViolation::ExplainCombination, $command);
            }
            return new ExplainConnectionStatement($origin, new NumericParameter(self::connection($command)), $options->format);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ExplainCombination, $command, $error);
        }
    }

    /**
     * Returns the connection id in decimal; a hexadecimal id is the same number, a fraction or exponent is rejected as the server does.
     * @throws InvalidSql
     */
    public static function connection(Node $command): string
    {
        $number = Tree::child($command, ['real_ulong_num']) ?? Tree::invalid($command, 'connection number');
        $text = Tree::text($number);
        if (preg_match('/^(?:0x([0-9a-f]+)|x\'([0-9a-f]*)\')$/Di', $text, $hex) === 1) {
            $digits = ltrim($hex[1] . ($hex[2] ?? ''), '0');
            return $digits === '' ? '0' : self::decimal($digits);
        }
        if (preg_match('/^[0-9]+$/D', $text) !== 1) {
            throw new InvalidSql(InputViolation::ExplainSetting, $number);
        }
        return $text;
    }

    /**
     * Converts a hexadecimal digit string of any length to decimal digits.
     */
    public static function decimal(string $hex): string
    {
        $decimal = '0';
        foreach (str_split(strtolower($hex)) as $digit) {
            $carry = (int) hexdec($digit);
            $result = '';
            foreach (array_reverse(str_split($decimal)) as $place) {
                $value = (int) $place * 16 + $carry;
                $result = ($value % 10) . $result;
                $carry = intdiv($value, 10);
            }
            $decimal = ($carry > 0 ? (string) $carry : '') . $result;
        }
        return ltrim($decimal, '0') === '' ? '0' : ltrim($decimal, '0');
    }
}
