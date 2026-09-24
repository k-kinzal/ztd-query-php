<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server\Change;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Server\Literals;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Configuration\Replication\Source;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads one option assignment of CHANGE REPLICATION SOURCE TO or CHANGE MASTER TO.
 * @visibility SqlSemantics
 */
final class SourceDefinitions
{
    /**
     * Options that MySQL accepts only as 0 or 1.
     */
    public const BINARY = ['REQUIRE_ROW_FORMAT', 'SOURCE_CONNECTION_AUTO_FAILOVER', 'GTID_ONLY'];

    /**
     * Reads the option keyword in either vocabulary and types its value.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $definition, Identifiers $identifiers): Source\SourceSetting
    {
        $tokens = $definition->tokens();
        $option = Source\SourceOption::spelled($tokens[0]->text ?? '') ?? throw new UnclassifiedSql('Unclassified replication source option: ' . $definition->toString());
        $value = $tokens[2] ?? throw new UnclassifiedSql('A replication source option requires a value.');
        try {
            return match (true) {
                in_array($option->value, Source\SourceOption::TEXTS, true) => new Source\SourceText($option, Literals::text($value)),
                in_array($option->value, Source\SourceOption::NUMBERS, true) => new Source\SourceNumber($option, Literals::text($value)),
                in_array($option->value, Source\SourceOption::FLAGS, true) => self::flag($option, Literals::text($value)),
                $option === Source\SourceOption::IgnoreServerIds => new Source\IgnoredServers(array_map(Literals::text(...), Tree::outer($definition, ['ignore_server_id']))),
                $option === Source\SourceOption::PrivilegeChecksUser => new Source\PrivilegeChecks(self::account(array_slice($tokens, 2), $identifiers)),
                $option === Source\SourceOption::RequireTablePrimaryKeyCheck => Source\PrimaryKeyCheck::from(strtoupper($value->text)),
                default => Source\AnonymousGtids::tryFrom(strtoupper($value->text)) ?? new Source\AnonymousGtidUuid(Literals::text($value)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ReplicationOption, $definition, $error);
        }
    }

    /**
     * Reads a switch as MySQL does: any nonzero integer is on; three options accept only 0 or 1, two of them only as integers.
     * @throws InvalidStructure
     */
    public static function flag(Source\SourceOption $option, \SqlSemantics\Model\Scalar\Value\Literal $value): Source\SourceFlag
    {
        ReplicationNumber::check($value, $option->value);
        $number = ReplicationNumber::magnitude($value);
        $integral = preg_match('/^(\d+|0x[0-9a-f]+|x\'[0-9a-f]*\')$/Di', $value->text) === 1;
        if (in_array($option->value, self::BINARY, true) && ($number > 1 || (!$integral && $option !== Source\SourceOption::RequireRowFormat))) {
            throw new InvalidStructure($option->value . ' accepts only 0 or 1.');
        }
        return new Source\SourceFlag($option, $number !== 0.0);
    }

    /**
     * Reads user[@host], or NULL for no account.
     * @param list<Token> $tokens
     */
    public static function account(array $tokens, Identifiers $identifiers): ?AccountName
    {
        if (count($tokens) === 1 && strtoupper($tokens[0]->text) === 'NULL') {
            return null;
        }
        return new AccountName(MySqlNames::read($tokens[0], $identifiers), isset($tokens[2]) ? MySqlNames::read($tokens[2], $identifiers) : null);
    }
}
