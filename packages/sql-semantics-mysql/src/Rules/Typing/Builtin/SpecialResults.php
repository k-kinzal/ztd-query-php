<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of the XML, GTID, statement digest and vector functions.
 *
 * EXTRACTVALUE and UPDATEXML answer a text in the collation their arguments aggregate to,
 * STATEMENT_DIGEST_TEXT one in the collation of its argument; all three are as long as 16777216
 * characters of the largest width of the set, a binary string when the collation is binary or
 * every argument is NULL.
 * STATEMENT_DIGEST and GTID_SUBTRACT answer a text in the connection collation: the digest is 64
 * hexadecimal digits, the GTID set as long as the first argument in bytes, two and a half times
 * the second, less 90; a result too long for a VARCHAR counts each character as many times as
 * the character set is wide, as the server does. A length below zero overflows to a long text,
 * as does a second argument of less than 36 bytes when the connection character set is one or
 * three bytes wide.
 * GTID_SUBSET, the waits for a replica and VECTOR_DIM answer integers; STRING_TO_VECTOR and TO_VECTOR a VECTOR of 16383
 * dimensions; VECTOR_TO_STRING and FROM_VECTOR a text of 1048512 characters in the connection
 * collation (verified on live 5.7.44, 8.0.44, 8.4 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xml-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/gtid-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html,
 * https://dev.mysql.com/doc/refman/9.1/en/vector-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class SpecialResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $vector = static fn (Invocation $call): Domain => new Domain(Kind::String, Field::Vector, 65532, Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Coercible);
        $text = static fn (Invocation $call): Domain => Domain::string(1048512, $call->settings->connection, Field::MediumBlob, Coercibility::Coercible);

        return [
            'EXTRACTVALUE' => fn (Invocation $call): ?Domain => $this->document($call, $call->domains, 'extractvalue'),
            'UPDATEXML' => fn (Invocation $call): ?Domain => $this->document($call, $call->domains, 'updatexml'),
            'STATEMENT_DIGEST_TEXT' => fn (Invocation $call): ?Domain => $this->document($call, [$call->domain(0)], 'statement_digest_text'),
            'STATEMENT_DIGEST' => static fn (Invocation $call): Domain => Domain::string(64, $call->settings->connection, Field::VarString, Coercibility::Coercible),
            'GTID_SUBSET' => static fn (Invocation $call): Domain => Domain::integer(),
            'GTID_SUBTRACT' => $this->subtraction(...),
            'WAIT_FOR_EXECUTED_GTID_SET' => static fn (Invocation $call): Domain => Domain::integer(),
            'SOURCE_POS_WAIT' => static fn (Invocation $call): Domain => Domain::integer(),
            'MASTER_POS_WAIT' => static fn (Invocation $call): Domain => Domain::integer(),
            'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS' => static fn (Invocation $call): Domain => Domain::integer(),
            'STRING_TO_VECTOR' => $vector,
            'TO_VECTOR' => $vector,
            'VECTOR_TO_STRING' => $text,
            'FROM_VECTOR' => $text,
            'VECTOR_DIM' => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 10),
        ];
    }

    /**
     * Resolves a text of 16777216 characters in the collation some arguments aggregate to, or null after a conflict.
     *
     * @param list<Domain> $domains The arguments whose collations take part
     */
    public function document(Invocation $call, array $domains, string $operation): ?Domain
    {
        $settled = $call->collations()->aggregate($domains, $operation, $call->derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        if (array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null) === []) {
            $collation = Collation::binary();
        }

        return Domain::string(16777216 * $collation->charset->maxLength, $collation, Field::LongBlob, $coercibility);
    }

    /**
     * Resolves GTID_SUBTRACT: a text as long as its formula allows, or a long text when it overflows.
     */
    public function subtraction(Invocation $call): Domain
    {
        $connection = $call->settings->connection;
        $width = $connection->charset->maxLength;
        $second = intdiv($call->domain(1)->kind === Kind::Null ? 0 : $call->domain(1)->byteLength() * 5, 2);
        $length = ($call->domain(0)->kind === Kind::Null ? 0 : $call->domain(0)->byteLength()) + $second - 90;
        $blob = $length * $width > 65535;
        if ($length < 0 || ($width % 2 === 1 && $second < 90) || ($blob && $length * $width * $width > 16777215)) {
            $grammar = $call->derivation->context->profile->grammar;
            $legacy = $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
            $overflow = $width === 3 && !$legacy ? 16777215 : 16777216;

            return Domain::string($overflow, $connection, $overflow > 16777215 ? Field::LongBlob : Field::MediumBlob, Coercibility::Coercible);
        }

        return $blob ? Domain::string($length * $width, $connection, Field::MediumBlob, Coercibility::Coercible) : Domain::string($length, $connection, Field::VarString, Coercibility::Coercible);
    }
}
