<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Type\Identity\Enumeration;
use SqlSemantics\Type\Identity\LabelSet;

/**
 * Reads declared enumeration labels as values rather than SQL fragments.
 * @visibility SqlSemantics
 */
final class LabelReader
{
    /**
     * @throws UnclassifiedSql
     */
    public static function read(Node $source, string $kind): Enumeration|LabelSet
    {
        $labels = [];
        foreach (Tree::outer($source, ['text_string', 'TEXT_STRING_sys']) as $label) {
            $token = $label->tokens()[0] ?? null;
            if ($token === null) {
                throw new UnclassifiedSql('A label type requires string labels.');
            }
            $value = (new \SqlSemantics\Binding\LiteralBinder(\SqlSemantics\Dialect::MySql))->bind($token);
            if (!$value instanceof \SqlSemantics\Model\Scalar\Value\Literal || !in_array($value->literalKind, [\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, \SqlSemantics\Model\Scalar\Value\LiteralKind::Binary, \SqlSemantics\Model\Scalar\Value\LiteralKind::BitString], true)) {
                throw new UnclassifiedSql('A label type requires classified string literals.');
            }
            $labels[] = $value;
        }
        if ($labels === []) {
            throw new UnclassifiedSql('A label type requires at least one label.');
        }
        $encoding = MySql\CharacterEncoding::read($source);
        return $kind === 'ENUM' ? new Enumeration($labels, $encoding->name, $encoding->binary) : new LabelSet($labels, $encoding->name, $encoding->binary);
    }
}
