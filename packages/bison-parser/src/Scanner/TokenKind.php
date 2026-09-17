<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

/**
 * The kinds of token Bison's own scanner produces from a grammar file.
 *
 * @visibility root
 */
enum TokenKind: string
{
    case Prologue = 'prologue';
    case Directive = 'directive';
    case Identifier = 'identifier';
    case IdentifierColon = 'identifier followed by a colon';
    case BracketedIdentifier = 'bracketed identifier';
    case CharLiteral = 'character literal';
    case String = 'string';
    case TranslatableString = 'translatable string';
    case Integer = 'integer';
    case Tag = 'tag';
    case TagAny = '<*>';
    case TagNone = '<>';
    case Code = 'braced code';
    case Predicate = 'predicate';
    case Colon = ':';
    case Equal = '=';
    case Pipe = '|';
    case Semicolon = ';';
    case Section = '%%';
    case Epilogue = 'epilogue';
    case Line = 'line directive';
    case End = 'end of file';
}
