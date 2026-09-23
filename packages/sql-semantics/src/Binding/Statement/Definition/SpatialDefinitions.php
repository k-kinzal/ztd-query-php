<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds spatial-definition DDL while leaving definition-text interpretation to the consumer.
 * @visibility SqlSemantics
 */
final class SpatialDefinitions
{
    /**
     * Separates declarations with required metadata from identifier-only removal requests.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::MySql || !in_array($source->name, ['create_srs_stmt', 'drop_srs_stmt'], true)) {
            return null;
        }
        $id = Tree::child($source, ['real_ulonglong_num']) ?? throw new UnclassifiedSql('A spatial definition requires its SRID.');
        $srid = self::identifier($id);
        if ($srid === 0) {
            throw new InvalidSql(InputViolation::SpatialReferenceId, $id);
        }
        if ($source->name === 'drop_srs_stmt') {
            return new Statement\DropSpatialReferenceSystemStatement($origin, $srid, Tree::child($source, ['if_exists']) !== null);
        }
        $tokens = $source->tokens();
        $policy = strtoupper($tokens[1]->text ?? '') === 'OR' ? CreationPolicy::Replace : (Tree::child($source, ['opt_if_not_exists']) === null ? CreationPolicy::RequireNew : CreationPolicy::IfNotExists);
        return new Statement\CreateSpatialReferenceSystemStatement($origin, $srid, SpatialMetadata::read($source), $policy);
    }

    /**
     * Decodes a bounded integer token, retaining its numeric identity rather than its numeral base.
     * @throws InvalidSql
     */
    public static function identifier(Node $source): int
    {
        $token = $source->tokens()[0];
        $text = $token->text;
        if ($token->name === 'HEX_NUM') {
            $digits = str_starts_with(strtolower($text), '0x') ? substr($text, 2) : substr($text, 2, -1);
            $digits = ltrim($digits, '0');
            if (strlen($digits) > 8) {
                throw new InvalidSql(InputViolation::SpatialReferenceId, $source);
            }
            $number = hexdec($digits === '' ? '0' : $digits);
            if (!is_int($number)) {
                throw new InvalidSql(InputViolation::SpatialReferenceId, $source);
            }
            return $number;
        }
        $digits = ltrim($text, '0');
        if (!ctype_digit($text) || strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '4294967295') > 0)) {
            throw new InvalidSql(InputViolation::SpatialReferenceId, $source);
        }
        return intval($digits === '' ? '0' : $digits);
    }
}
