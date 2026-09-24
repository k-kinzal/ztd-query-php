<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;
use SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds collation and encoding conversion commands.
 * @visibility SqlSemantics
 */
final class Locales
{
    /**
     * Encoding names are matched as the server matches them: case-insensitively and ignoring punctuation.
     */
    public const ENCODINGS = [
        'abc' => 'WIN1258', 'alt' => 'WIN866', 'big5' => 'BIG5', 'euccn' => 'EUC_CN', 'eucjis2004' => 'EUC_JIS_2004', 'eucjp' => 'EUC_JP', 'euckr' => 'EUC_KR', 'euctw' => 'EUC_TW',
        'gb18030' => 'GB18030', 'gbk' => 'GBK', 'iso88591' => 'LATIN1', 'iso885910' => 'LATIN6', 'iso885913' => 'LATIN7', 'iso885914' => 'LATIN8', 'iso885915' => 'LATIN9', 'iso885916' => 'LATIN10',
        'iso88592' => 'LATIN2', 'iso88593' => 'LATIN3', 'iso88594' => 'LATIN4', 'iso88595' => 'ISO_8859_5', 'iso88596' => 'ISO_8859_6', 'iso88597' => 'ISO_8859_7', 'iso88598' => 'ISO_8859_8', 'iso88599' => 'LATIN5',
        'johab' => 'JOHAB', 'koi8' => 'KOI8R', 'koi8r' => 'KOI8R', 'koi8u' => 'KOI8U', 'latin1' => 'LATIN1', 'latin10' => 'LATIN10', 'latin2' => 'LATIN2', 'latin3' => 'LATIN3', 'latin4' => 'LATIN4',
        'latin5' => 'LATIN5', 'latin6' => 'LATIN6', 'latin7' => 'LATIN7', 'latin8' => 'LATIN8', 'latin9' => 'LATIN9', 'mskanji' => 'SJIS', 'muleinternal' => 'MULE_INTERNAL', 'shiftjis' => 'SJIS',
        'shiftjis2004' => 'SHIFT_JIS_2004', 'sjis' => 'SJIS', 'sqlascii' => 'SQL_ASCII', 'tcvn' => 'WIN1258', 'tcvn5712' => 'WIN1258', 'uhc' => 'UHC', 'unicode' => 'UTF8', 'utf8' => 'UTF8', 'vscii' => 'WIN1258',
        'win' => 'WIN1251', 'win1250' => 'WIN1250', 'win1251' => 'WIN1251', 'win1252' => 'WIN1252', 'win1253' => 'WIN1253', 'win1254' => 'WIN1254', 'win1255' => 'WIN1255', 'win1256' => 'WIN1256',
        'win1257' => 'WIN1257', 'win1258' => 'WIN1258', 'win866' => 'WIN866', 'win874' => 'WIN874', 'win932' => 'SJIS', 'win936' => 'GBK', 'win949' => 'UHC', 'win950' => 'BIG5',
        'windows1250' => 'WIN1250', 'windows1251' => 'WIN1251', 'windows1252' => 'WIN1252', 'windows1253' => 'WIN1253', 'windows1254' => 'WIN1254', 'windows1255' => 'WIN1255', 'windows1256' => 'WIN1256',
        'windows1257' => 'WIN1257', 'windows1258' => 'WIN1258', 'windows866' => 'WIN866', 'windows874' => 'WIN874', 'windows932' => 'SJIS', 'windows936' => 'GBK', 'windows949' => 'UHC', 'windows950' => 'BIG5',
    ];

    /**
     * CREATE CONVERSION names two known encodings other than SQL_ASCII and the conversion function.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function conversion(Origin $origin, Node $source, QueryContext $context): Statement\CreateConversionStatement
    {
        $names = Tree::outer($source, ['any_name']);
        $encodings = array_map(static fn (Node $constant): ?ServerEncoding => self::encoding(TypeDefinitions::label($constant, $context)), Tree::outer($source, ['Sconst']));
        $from = $encodings[0] ?? null;
        $to = $encodings[1] ?? null;
        if ($from === null || $to === null || $from === ServerEncoding::SqlAscii || $to === ServerEncoding::SqlAscii) {
            throw new InvalidSql(InputViolation::ConversionEncoding, $source);
        }
        $name = ObjectAddresses::name($names[0] ?? throw new UnclassifiedSql('A conversion requires its name.'), $context, 2);
        $function = ObjectAddresses::name($names[1] ?? throw new UnclassifiedSql('A conversion requires its function.'), $context, 2);
        return new Statement\CreateConversionStatement($origin, $name, $from, $to, $function, Tree::child($source, ['opt_default']) !== null);
    }

    /**
     * Resolves an encoding name or alias; unknown names resolve to null.
     */
    public static function encoding(string $name): ?ServerEncoding
    {
        $key = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $name));
        return ServerEncoding::tryFrom(self::ENCODINGS[$key] ?? '');
    }

    /**
     * CREATE COLLATION copies an existing collation with FROM, or reads provider and locale settings that must each be given once.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function collation(Origin $origin, Node $source, QueryContext $context): Statement\CreateCollationStatement|Statement\CopyCollationStatement
    {
        $names = Tree::outer($source, ['any_name']);
        $name = ObjectAddresses::name($names[0] ?? throw new UnclassifiedSql('A collation requires its name.'), $context, 2);
        $ifNotExists = strtoupper($source->tokens()[2]->text ?? '') === 'IF';
        if (isset($names[1])) {
            return new Statement\CopyCollationStatement($origin, $name, ObjectAddresses::name($names[1], $context, 2), $ifNotExists);
        }
        $elements = DefinitionElement::named(DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context), ['from', 'locale', 'lc_collate', 'lc_ctype', 'provider', 'deterministic', 'rules', 'version'], false);
        if (isset($elements['from'])) {
            return count($elements) === 1 ? new Statement\CopyCollationStatement($origin, $name, DefinitionArguments::objectName($elements['from'], $context), $ifNotExists) : throw new InvalidSql(InputViolation::DefinitionRequirement, $source);
        }
        $text = static fn (string $key): ?string => isset($elements[$key]) ? DefinitionArguments::text($elements[$key]->argument ?? throw new InvalidSql(InputViolation::DefinitionArgument, $elements[$key]->source), $context) : null;
        $provider = CollationProvider::tryFrom(strtolower($text('provider') ?? 'libc')) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $elements['provider']->source ?? $source);
        $deterministic = $elements['deterministic']->argument ?? null;
        try {
            return new Statement\CreateCollationStatement($origin, $name, $provider, $text('locale'), $text('lc_collate'), $text('lc_ctype'), $deterministic === null || DefinitionArguments::boolean($deterministic, $context), $text('rules'), $text('version'), $ifNotExists);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * ALTER COLLATION ... REFRESH VERSION names one collation.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function refresh(Origin $origin, Node $source, QueryContext $context): Statement\RefreshCollationVersionStatement
    {
        return new Statement\RefreshCollationVersionStatement($origin, ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A collation refresh requires its collation.'), $context, 2));
    }
}
