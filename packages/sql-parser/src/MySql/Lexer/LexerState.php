<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

/**
 * Where MySQL's lexer resumes after a token that decides how the next one is read.
 *
 * MySQL reads a word after `.` as an identifier even when it is a keyword,
 * reads a host name after `@`, and reads the name after `@@` as a keyword or
 * identifier; only the ordinary start state skips whitespace first.
 *
 * @visibility root
 */
enum LexerState
{
    case Start;
    case IdentifierSeparator;
    case IdentifierStart;
    case Hostname;
    case SystemVariable;
    case IdentifierOrKeyword;
}
