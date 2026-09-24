<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\MySqlNumbers;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Statement\Definition\MySql\Server\AlterServerStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateServerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds CREATE SERVER and ALTER SERVER with their connection options.
 * @visibility SqlSemantics
 */
final class Servers
{
    /**
     * Reads the server name, the wrapper of a new server, and the final value of each option.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $source, bool $create, Identifiers $identifiers): BoundStatement
    {
        $names = array_map(static fn (Node $name): string => MySqlNames::read($name->tokens()[0], $identifiers), Tree::outer($source, ['ident_or_text']));
        $name = $names[0] ?? throw new UnclassifiedSql('A server definition requires its name.');
        if (!$create) {
            return new AlterServerStatement($origin, $name, self::options($source, $identifiers));
        }
        if ($name === '') {
            throw new InvalidSql(InputViolation::ServerDefinition, $source);
        }
        return new CreateServerStatement($origin, $name, $names[1] ?? throw new UnclassifiedSql('CREATE SERVER requires its wrapper.'), self::options($source, $identifiers));
    }

    /**
     * Keeps the last value of each repeated option, as the server does.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function options(Node $source, Identifiers $identifiers): ServerOptions
    {
        $texts = [];
        $port = null;
        foreach (Tree::outer($source, ['server_option']) as $option) {
            $keyword = preg_replace('/_SYM$/D', '', $option->tokens()[0]->name ?? '') ?? '';
            if ($keyword === 'PORT') {
                $port = MySqlNumbers::read(Tree::outer($option, ['ulong_num'])[0] ?? throw new UnclassifiedSql('PORT requires its number.'), InputViolation::ServerDefinition);
                continue;
            }
            $texts[$keyword] = MySqlNames::read((Tree::outer($option, ['TEXT_STRING_sys'])[0] ?? throw new UnclassifiedSql('A server option requires its string.'))->tokens()[0], $identifiers);
        }
        return new ServerOptions($texts['USER'] ?? null, $texts['HOST'] ?? null, $texts['DATABASE'] ?? null, $texts['OWNER'] ?? null, $texts['PASSWORD'] ?? null, $texts['SOCKET'] ?? null, $port);
    }
}
