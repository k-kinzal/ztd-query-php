@manual
Feature: Symbols, Terminal and Nonterminal
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.2      Symbols, Terminal and Nonterminal            manual:Symbols

  Terminal symbols are written as identifiers, as C character constants, or as
  C string constants; nonterminal symbols are identifiers.

  @manual:Symbols
  Scenario: Symbol names may contain letters, underscores, periods, and non-initial digits and dashes
    Given the grammar file:
      """
      %token WITH-DASH WITH.PERIOD with_underscore d1g1t5
      %%
      .start: WITH-DASH WITH.PERIOD with_underscore d1g1t5;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry WITH-DASH
        SymbolEntry WITH.PERIOD
        SymbolEntry with_underscore
        SymbolEntry d1g1t5
      %%
      Rule .start
        Alternative
          SymbolItem WITH-DASH
          SymbolItem WITH.PERIOD
          SymbolItem with_underscore
          SymbolItem d1g1t5
      """

  @manual:Symbols
  Scenario: A dash cannot start a symbol name
    Given the grammar file:
      """
      %token -DASH
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:Symbols
  Scenario: A digit cannot start a symbol name
    Given the grammar file:
      """
      %token 1NV4L1D
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:Symbols
  Scenario: A named token kind is written as an identifier and declared with %token
    Given the grammar file:
      """
      %token NUM
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry NUM
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Symbols
  Scenario: A character token kind is written like a C character constant and needs no declaration
    Given the grammar file:
      """
      %%
      exp: exp '+' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
      """

  @manual:Symbols
  Scenario: The usual C escape sequences can be used in character literals
    Given the grammar file:
      """
      %%
      exp: '\n' '\t' '\\' '\'' '"' '\101' '\x41' '\a' '\?';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem '\n'
          SymbolItem '\t'
          SymbolItem '\\'
          SymbolItem '\''
          SymbolItem '"'
          SymbolItem 'A' spelled '\101'
          SymbolItem 'A' spelled '\x41'
          SymbolItem '\x07' spelled '\a'
          SymbolItem '?' spelled '\?'
      """

  @manual:Symbols
  Scenario: The null character must not be used as a character literal
    Given the grammar file:
      """
      %%
      exp: '\0';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:Symbols
  Scenario: Trigraphs have no special meaning in character literals, so '??=' is not one character
    Given the grammar file:
      """
      %%
      exp: '??=';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:Symbols
  Scenario: Backslash-newline is not allowed in a character literal
    Given the grammar file:
      """
      %%
      exp: '\
      ';
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:Symbols
  Scenario: A literal string token is written like a C string constant and needs no declaration
    Given the grammar file:
      """
      %%
      exp: exp "<=" exp;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem "<="
          SymbolItem exp
      """

  @manual:Symbols
  Scenario: The usual C escape sequences can be used in string literals
    Given the grammar file:
      """
      %%
      exp: "\x3c=" "a\tb" "\"q\"";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem "<=" spelled "\x3c="
          SymbolItem "a\tb"
          SymbolItem "\"q\""
      """

  @manual:Symbols
  Scenario: A null character must not be used within a string literal
    Given the grammar file:
      """
      %%
      exp: "a\0b";
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:Symbols
  Scenario: Trigraphs have no special meaning in string literals
    Given the grammar file:
      """
      %%
      exp: "??=";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem "??="
      """

  @manual:Symbols
  Scenario: Backslash-newline is not allowed in a string literal
    Given the grammar file:
      """
      %%
      exp: "a\
      b";
      """
    When the file is parsed
    Then parsing fails at line 2 column 6

  @manual:Symbols
  Scenario: A literal string token may be given a symbolic name as an alias with %token
    Given the grammar file:
      """
      %token LE "<="
      %%
      exp: exp LE exp | exp "<=" exp;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry LE "<="
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem LE
          SymbolItem exp
        Alternative
          SymbolItem exp
          SymbolItem "<="
          SymbolItem exp
      """

  @manual:Symbols
  Scenario: Every nonnull character of the basic execution character set of Standard C may be a character token
    Given the grammar file:
      """
      %%
      digits: '0' '1' '2' '3' '4' '5' '6' '7' '8' '9';
      letters:
        'a' 'b' 'c' 'd' 'e' 'f' 'g' 'h' 'i' 'j' 'k' 'l' 'm' 'n' 'o' 'p' 'q' 'r' 's' 't' 'u' 'v' 'w' 'x' 'y' 'z'
        'A' 'B' 'C' 'D' 'E' 'F' 'G' 'H' 'I' 'J' 'K' 'L' 'M' 'N' 'O' 'P' 'Q' 'R' 'S' 'T' 'U' 'V' 'W' 'X' 'Y' 'Z'
      ;
      others:
        '\a' '\b' '\t' '\n' '\v' '\f' '\r' ' ' '!' '"' '#' '%' '&' '\'' '(' ')' '*' '+' ','
        '-' '.' '/' ':' ';' '<' '=' '>' '?' '[' '\\' ']' '^' '_' '{' '|' '}' '~'
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule digits
        Alternative
          SymbolItem '0'
          SymbolItem '1'
          SymbolItem '2'
          SymbolItem '3'
          SymbolItem '4'
          SymbolItem '5'
          SymbolItem '6'
          SymbolItem '7'
          SymbolItem '8'
          SymbolItem '9'
      Rule letters
        Alternative
          SymbolItem 'a'
          SymbolItem 'b'
          SymbolItem 'c'
          SymbolItem 'd'
          SymbolItem 'e'
          SymbolItem 'f'
          SymbolItem 'g'
          SymbolItem 'h'
          SymbolItem 'i'
          SymbolItem 'j'
          SymbolItem 'k'
          SymbolItem 'l'
          SymbolItem 'm'
          SymbolItem 'n'
          SymbolItem 'o'
          SymbolItem 'p'
          SymbolItem 'q'
          SymbolItem 'r'
          SymbolItem 's'
          SymbolItem 't'
          SymbolItem 'u'
          SymbolItem 'v'
          SymbolItem 'w'
          SymbolItem 'x'
          SymbolItem 'y'
          SymbolItem 'z'
          SymbolItem 'A'
          SymbolItem 'B'
          SymbolItem 'C'
          SymbolItem 'D'
          SymbolItem 'E'
          SymbolItem 'F'
          SymbolItem 'G'
          SymbolItem 'H'
          SymbolItem 'I'
          SymbolItem 'J'
          SymbolItem 'K'
          SymbolItem 'L'
          SymbolItem 'M'
          SymbolItem 'N'
          SymbolItem 'O'
          SymbolItem 'P'
          SymbolItem 'Q'
          SymbolItem 'R'
          SymbolItem 'S'
          SymbolItem 'T'
          SymbolItem 'U'
          SymbolItem 'V'
          SymbolItem 'W'
          SymbolItem 'X'
          SymbolItem 'Y'
          SymbolItem 'Z'
      Rule others
        Alternative
          SymbolItem '\x07' spelled '\a'
          SymbolItem '\x08' spelled '\b'
          SymbolItem '\t'
          SymbolItem '\n'
          SymbolItem '\x0b' spelled '\v'
          SymbolItem '\x0c' spelled '\f'
          SymbolItem '\r'
          SymbolItem ' '
          SymbolItem '!'
          SymbolItem '"'
          SymbolItem '#'
          SymbolItem '%'
          SymbolItem '&'
          SymbolItem '\''
          SymbolItem '('
          SymbolItem ')'
          SymbolItem '*'
          SymbolItem '+'
          SymbolItem ','
          SymbolItem '-'
          SymbolItem '.'
          SymbolItem '/'
          SymbolItem ':'
          SymbolItem ';'
          SymbolItem '<'
          SymbolItem '='
          SymbolItem '>'
          SymbolItem '?'
          SymbolItem '['
          SymbolItem '\\'
          SymbolItem ']'
          SymbolItem '^'
          SymbolItem '_'
          SymbolItem '{'
          SymbolItem '|'
          SymbolItem '}'
          SymbolItem '~'
      """

  @manual:Symbols
  Scenario: error is a terminal symbol reserved for error recovery and may be used in rules
    Given the grammar file:
      """
      %%
      stmt: error ';';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmt
        Alternative
          SymbolItem error
          SymbolItem ';'
      """
