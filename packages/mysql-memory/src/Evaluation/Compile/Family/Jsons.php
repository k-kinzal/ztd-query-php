<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles the JSON operators: `col->'path'`, `col->>'path'` and `value MEMBER OF (array)`.
 *
 * A JSON argument is a JSON value, or a string read as a JSON text after it is converted to
 * utf8mb4; a value of another type is refused for each row, even NULL (ER_INVALID_TYPE_FOR_JSON),
 * a binary string is refused (ER_INVALID_JSON_CHARSET, followed by ER_INVALID_TYPE_FOR_JSON), and a text that is no JSON document is
 * refused with the message of the parser (ER_INVALID_JSON_TEXT_IN_PARAM). `->` is
 * JSON_EXTRACT() and `->>` JSON_UNQUOTE(JSON_EXTRACT()); the path is read once the document is,
 * so a NULL document hides a path that is not valid. MEMBER OF is NULL when its value is NULL,
 * before the array is checked, or when the array is NULL; it tells whether the value equals an
 * element of the array, or the array itself when it is no array. The value is a JSON value made
 * from its SQL type: a number keeps its type, a string is a JSON string, a binary string an opaque
 * value, a temporal value a JSON temporal value, and a predicate a JSON boolean (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Jsons
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles `col->'path'` and `col->>'path'`.
     *
     * @throws SqlError When the column cannot be compiled
     */
    public function extraction(JsonExtraction $node, Scope $scope): Evaluable
    {
        $column = $this->compiler->compile($node->column, $scope);
        $path = $node->path->value;
        $unquote = $node->unquote;
        $routine = new Routine($unquote ? '->>' : '->', 1, 1, static function (Frame $f, array $a) use ($path, $unquote): ?string {
            $document = Jsons::document($a[0]->evaluate($f), $a[0]->domain(), 1, 'json_extract');
            if ($document === null) {
                return null;
            }
            try {
                $found = JsonPath::parse($path)->extract($document);
            } catch (JsonSyntax $failure) {
                throw DataError::InvalidJsonPath->error($failure->position);
            }
            if ($found === null) {
                return null;
            }

            return $unquote ? $found->unquoted() : $found->text();
        });

        return new Call($routine, [$column], $this->compiler->domain($node));
    }

    /**
     * Compiles `value MEMBER OF (array)`.
     *
     * @throws SqlError When an operand cannot be compiled
     */
    public function member(MemberOf $node, Scope $scope): Evaluable
    {
        $value = $this->compiler->compile($node->operand, $scope);
        $array = $this->compiler->compile($node->array, $scope);
        $boolean = $this->boolean($node->operand);
        $routine = new Routine('MEMBER OF', 2, 2, static function (Frame $f, array $a) use ($boolean): ?int {
            $value = $a[0]->evaluate($f);
            if ($value === null) {
                return null;
            }
            $document = Jsons::document($a[1]->evaluate($f), $a[1]->domain(), 2, 'member of');
            if ($document === null) {
                return null;
            }
            $member = Jsons::value($value, $a[0]->domain(), $boolean);
            foreach ($document->type === JsonKind::Array ? $document->children() : [$document] as $element) {
                if ($member->equals($element)) {
                    return 1;
                }
            }

            return 0;
        });

        return new Call($routine, [$value, $array], $this->compiler->domain($node));
    }

    /**
     * Tells whether an expression is a predicate, whose value becomes a JSON boolean.
     */
    public function boolean(Scalar $node): bool
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return $node instanceof BooleanLiteral || $node instanceof Comparison || $node instanceof Logical || $node instanceof Not || $node instanceof NullTest || $node instanceof TruthTest
            || $node instanceof Between || $node instanceof InList || $node instanceof Like || $node instanceof Regexp || $node instanceof SoundsLike || $node instanceof MemberOf
            || $node instanceof Exists || $node instanceof InQuery || $node instanceof QuantifiedComparison;
    }

    /**
     * Reads a JSON argument of a function: a JSON value, or a string holding a JSON text; NULL is null.
     *
     * @param int $position The position of the argument, counted from 1
     * @param string $function The function name the server writes in its messages
     *
     * @throws SqlError When the argument is of another type, a binary string, or no JSON text
     */
    public static function document(int|float|string|null $value, Domain $domain, int $position, string $function): ?JsonNode
    {
        if ($domain->kind !== Kind::String && $domain->kind !== Kind::Json && $domain->kind !== Kind::Null) {
            throw DataError::InvalidJsonType->error($position, $function);
        }
        if ($value === null) {
            return null;
        }
        $text = (string) $value;
        if ($domain->kind === Kind::String) {
            $charset = $domain->collation->charset;
            if ($charset === Charset::binary()) {
                throw new SqlError(DataError::InvalidJsonCharset, DataError::InvalidJsonCharset->message('binary'), null, [[DataError::InvalidJsonType->value, DataError::InvalidJsonType->message($position, $function)]]);
            }
            $text = Encoding::convert($text, $charset, Charset::known('utf8mb4'));
        }
        try {
            return JsonNode::parse($text);
        } catch (JsonSyntax $failure) {
            $error = DataError::InvalidJsonTextInParameter->message($position, $function, $failure->reason, $failure->position);
            if ($failure->deep) {
                throw new SqlError(DataError::JsonDocumentTooDeep, DataError::JsonDocumentTooDeep->message(), $failure, [[DataError::InvalidJsonTextInParameter->value, $error]]);
            }

            throw new SqlError(DataError::InvalidJsonTextInParameter, $error, $failure);
        }
    }

    /**
     * Makes the JSON value of an SQL value that is not NULL.
     *
     * @param bool $boolean Whether the value is that of a predicate
     */
    public static function value(int|float|string $value, Domain $domain, bool $boolean): JsonNode
    {
        if ($boolean) {
            return new JsonNode(JsonKind::Boolean, (int) $value !== 0);
        }
        $text = (string) Convert::toText($value, $domain);

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => new JsonNode(JsonKind::Integer, $text),
            Kind::Double => new JsonNode(JsonKind::Double, (float) $value),
            Kind::Decimal => new JsonNode(JsonKind::Decimal, $text),
            Kind::String => $domain->collation->charset === Charset::binary() ? new JsonNode(JsonKind::Opaque, $text) : new JsonNode(JsonKind::String, Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4'))),
            Kind::Date => new JsonNode(JsonKind::Date, $text),
            Kind::Time => new JsonNode(JsonKind::Time, $text),
            Kind::DateTime => new JsonNode(JsonKind::DateTime, $text),
            Kind::Json => JsonNode::parse($text),
            Kind::Bit, Kind::Null => new JsonNode(JsonKind::Opaque, $text),
        };
    }
}
