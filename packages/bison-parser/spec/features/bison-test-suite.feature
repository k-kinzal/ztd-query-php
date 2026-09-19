Feature: Behaviour fixed by Bison's own test suite
  Where the manual is silent, the tests shipped with GNU Bison 3.8.2 fix the
  behaviour.  Each scenario names the test it paraphrases (file and title).

  Scenario: tests/input.at "Deprecated directives": old spellings with underscores, and = after a value directive, are still read
    Given the grammar file:
      """
      %default_prec
      %error_verbose
      %expect_rr 0
      %file-prefix = "foo"
      %file-prefix
       =
      "bar"
      %fixed-output_files
      %fixed_output-files
      %fixed-output-files
      %name-prefix= "foo"
      %no-default_prec
      %no_default-prec
      %no_lines
      %output = "output.c"
      %pure_parser
      %token_table
      %error-verbose
      %glr-parser
      %name-prefix "bar"
      %defines "header.h"
      %%
      exp : '0'
      """
    When the file is parsed
    Then the tree is:
      """
      Flag default-prec spelled %default_prec
      Flag error-verbose spelled %error_verbose
      Expect reduce/reduce 0
      Option file-prefix "foo"
      Option file-prefix "bar"
      Flag fixed-output-files spelled %fixed-output_files
      Flag fixed-output-files spelled %fixed_output-files
      Flag fixed-output-files
      Option name-prefix "foo"
      Flag no-default-prec spelled %no-default_prec
      Flag no-default-prec spelled %no_default-prec
      Flag no-lines spelled %no_lines
      Option output "output.c"
      Flag pure-parser spelled %pure_parser
      Flag token-table spelled %token_table
      Flag error-verbose
      Flag glr-parser
      Option name-prefix "bar"
      Option header "header.h" spelled %defines
      %%
      Rule exp
        Alternative
          SymbolItem '0'
      """

  Scenario: tests/input.at "Non-deprecated directives": the current spellings
    Given the grammar file:
      """
      %default-prec
      %define parse.error verbose
      %expect-rr 42
      %file-prefix "foo"
      %file-prefix
      "bar"
      %no-default-prec
      %no-lines
      %output "foo"
      %token-table
      %% exp : '0'
      """
    When the file is parsed
    Then the tree is:
      """
      Flag default-prec
      Define parse.error = verbose
      Expect reduce/reduce 42
      Option file-prefix "foo"
      Option file-prefix "bar"
      Flag no-default-prec
      Flag no-lines
      Option output "foo"
      Flag token-table
      %%
      Rule exp
        Alternative
          SymbolItem '0'
      """

  Scenario: tests/input.at "Torturing the Scanner": a lone brace among the declarations is an error
    Given the grammar file:
      """
      {}
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

  Scenario: tests/input.at "Torturing the Scanner": %{ and %} inside a comment or a string do not end the prologue
    Given the grammar file:
      """
      %{
      /* This is seen in GCC: a %{ and %} in middle of a comment. */
      const char *foo = "So %{ and %} can be here too.";
      %}
      /* %{ and %} can be here too. */
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n/* This is seen in GCC: a %{ and %} in middle of a comment. */\nconst char *foo = "So %{ and %} can be here too.";\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: tests/input.at "Torturing the Scanner": backslash-newlines inside a comment in the prologue are part of the comment
    Given the grammar file:
      """
      %{
      /\
      * A comment with backslash-newlines in it. %} *\
      \
      /
      %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n/\\\n* A comment with backslash-newlines in it. %} *\\\n\\\n/\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: tests/input.at "Torturing the Scanner": backslash-newlines inside a string in the prologue are part of the string
    Given the grammar file:
      """
      %{
      char str[] = "\\
      " A string with backslash-newlines in it %{ %} \\
      \
      "";
      %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\nchar str[] = "\\\\\n" A string with backslash-newlines in it %{ %} \\\\\n\\\n"";\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: tests/input.at "Torturing the Scanner": semicolons may separate the alternatives of a rule
    Given the grammar file:
      """
      %%
      output.or.oline.opt: %empty;|oline;;|output;;;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule output.or.oline.opt
        Alternative
          EmptyItem
        Alternative
          SymbolItem oline
        Alternative
          SymbolItem output
      """

  Scenario: tests/input.at "Torturing the Scanner": quotes inside braced code are kept
    Given the grammar file:
      """
      %token COMMENT_CLOSE "*/"
      %token COMMENT       "/* comment */"
      %%
      exp: '[' '\1' '$' '@' '{'
        {
          /* Exercise quotes in braces.  */
          char tmp[] = "[%c],\n";
          printf (tmp, $1);
        }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry COMMENT_CLOSE "*/"
      SymbolDeclaration token
        SymbolEntry COMMENT "/* comment */"
      %%
      Rule exp
        Alternative
          SymbolItem '['
          SymbolItem '\x01' spelled '\1'
          SymbolItem '$'
          SymbolItem '@'
          SymbolItem '{'
          Action {\n    /* Exercise quotes in braces.  */\n    char tmp[] = "[%c],\\n";\n    printf (tmp, $1);\n  }
      """

  Scenario: tests/diagnostics.at "Line is too short, and then you die": a #line directive in the grammar file is read
    Given the grammar file:
      """
      #line 12
      %token foo 123
      %%
      exp: foo;
      """
    When the file is parsed
    Then the tree is:
      """
      Line 12
      SymbolDeclaration token
        SymbolEntry foo 123
      %%
      Rule exp
        Alternative
          SymbolItem foo
      """

  Scenario: tests/diagnostics.at "Line is too short, and then you die": #line may name the file
    Given the grammar file:
      """
      #line 1 "/dev/stdout"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Line 1 "/dev/stdout"
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario Outline: tests/input.at "Bad escapes in literals": <literal> is rejected
    Given the grammar file:
      """
      %%
      start: <literal>;
      """
    When the file is parsed
    Then parsing fails at line 2 column 8

    Examples:
      | literal        |
      | '\777'         |
      | '\0'           |
      | '\xfff'        |
      | '\x0'          |
      | '\uffff'     |
      | '\u0000'       |
      | '\Uffffffff'   |
      | '\U00000000'   |
      | '\ '           |
      | '\A'           |
      | "\0"           |
      | "\x100"        |

  Scenario: tests/input.at "Bad character literals": an empty character literal is an error
    Given the grammar file:
      """
      %%
      start: '';
      """
    When the file is parsed
    Then parsing fails at line 2 column 8

  Scenario: tests/input.at "Bad character literals": extra characters in a character literal are an error
    Given the grammar file:
      """
      %%
      start: 'ab';
      """
    When the file is parsed
    Then parsing fails at line 2 column 8

  Scenario: tests/input.at "Bad character literals": a character literal open at the end of the line is an error
    Given the grammar file:
      """
      %%
      start: '
      """
    When the file is parsed
    Then parsing fails at line 2 column 8

  Scenario: tests/input.at "Bad character literals": a character literal open at the end of the file is an error
    Given the grammar file without a final newline:
      """
      %%
      start: 'ab
      """
    When the file is parsed
    Then parsing fails at line 2 column 8

  Scenario: tests/input.at "Unclosed constructs": a string open at the end of the line is an error
    Given the grammar file:
      """
      %token A "a
      %token B "b"
      %%
      start: %empty;
      """
    When the file is parsed
    Then parsing fails at line 1 column 10

  Scenario: tests/input.at "Unclosed constructs": braced code open at the end of the file is an error
    Given the grammar file:
      """
      %%
      start: %empty;
      %destructor { free ($$)
      """
    When the file is parsed
    Then parsing fails at line 3 column 13

  Scenario Outline: tests/input.at "Unexpected end of file": <construct> open at the end of the file is an error
    Given the grammar file without a final newline:
      """
      %token FOO <construct>
      """
    When the file is parsed
    Then parsing fails at line 1 column 12

    Examples:
      | construct |
      | '         |
      | '\        |
      | "         |
      | "\        |
      | _("       |
      | _("\      |

  Scenario: tests/input.at "Unexpected end of file": an empty file is an error
    Given the grammar file without a final newline:
      """
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

  Scenario Outline: tests/input.at "Invalid inputs": <input> is not valid at the start of the file
    Given the grammar file:
      """
      <input>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

    Examples:
      | input             |
      | ?                 |
      | %&                |
      | %a-does-not-exist |
      | %-                |
      | %{                |

  Scenario: tests/input.at "Invalid inputs": a closing brace outside braced code is an error
    Given the grammar file:
      """
      %%
      default: 'a' }
      """
    When the file is parsed
    Then parsing fails at line 2 column 14

  Scenario: tests/input.at "Symbols": periods are letters and may start an identifier
    Given the grammar file:
      """
      %token .GOOD
      %%
      start: .GOOD;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry .GOOD
      %%
      Rule start
        Alternative
          SymbolItem .GOOD
      """

  Scenario Outline: tests/input.at "Symbols": digits and dashes cannot start an identifier, so <name> is rejected
    Given the grammar file:
      """
      %token <name>
      %%
      start: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

    Examples:
      | name    |
      | -GOOD   |
      | 1NV4L1D |
      | -123    |

  Scenario: tests/input.at "Numbered tokens": hexadecimal codes are read as numbers
    Given the grammar file:
      """
      %token DECIMAL_1     11259375
               HEXADECIMAL_1 0xabcdef
               HEXADECIMAL_2 0xFEDCBA
               DECIMAL_2     16702650
      %%
      start: DECIMAL_1 HEXADECIMAL_2;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry DECIMAL_1 11259375
        SymbolEntry HEXADECIMAL_1 11259375
        SymbolEntry HEXADECIMAL_2 16702650
        SymbolEntry DECIMAL_2 16702650
      %%
      Rule start
        Alternative
          SymbolItem DECIMAL_1
          SymbolItem HEXADECIMAL_2
      """

  Scenario Outline: tests/input.at "Numbered tokens": <number> is out of range
    Given the grammar file:
      """
      %token TOO_LARGE <number>
      %%
      start: TOO_LARGE;
      """
    When the file is parsed
    Then parsing fails at line 1 column 18

    Examples:
      | number                 |
      | 999999999999999999999  |
      | 0xFFFFFFFFFFFFFFFFFFF  |
      | 2147483648             |

  Scenario: tests/input.at "String aliases for character tokens": a character token may have a string alias
    Given the grammar file:
      """
      %token 'a' "a"
      %%
      start: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry 'a' "a"
      %%
      Rule start
        Alternative
          SymbolItem 'a'
      """

  Scenario: tests/input.at "Typed symbol aliases": a semicolon after %union is tolerated
    Given the grammar file:
      """
      %union
      {
        int val;
      };
      %token <val> MY_TOKEN "MY TOKEN"
      %type <val> exp
      %%
      exp: "MY TOKEN";
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration {\n  int val;\n}
      SymbolDeclaration token
        SymbolEntry <val> MY_TOKEN "MY TOKEN"
      SymbolDeclaration type
        SymbolEntry <val> exp
      %%
      Rule exp
        Alternative
          SymbolItem "MY TOKEN"
      """

  Scenario: tests/input.at "Multiple %code": every occurrence is kept
    Given the grammar file:
      """
      %code {#include <assert.h>}
      %code {#define A B}
      %code {#define B C}
      %code {
      }
      %%
      start: %empty;
      """
    When the file is parsed
    Then the tree is:
      """
      Code {#include <assert.h>}
      Code {#define A B}
      Code {#define B C}
      Code {\n}
      %%
      Rule start
        Alternative
          EmptyItem
      """

  Scenario: tests/input.at "Stray $ or @": stray $ and @ in code are kept verbatim
    Given the grammar file:
      """
      %type <TYPE> exp
      %token <TYPE> TOK TOK2
      %destructor     { $%; @%; } <*> exp TOK;
      %initial-action { $%; @%; };
      %printer        { $%; @%; } <*> exp TOK;
      %{ $ @ %} // Should not warn.
      %%
      exp: TOK        { $%; @%; $$ = $1; }
         | 'a'        { $<->1; $$ = 1; }
         | 'b'        { $<foo->bar>$; }
      %%
      $ @ // Should not warn.
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration type
        SymbolEntry <TYPE> exp
      SymbolDeclaration token
        SymbolEntry <TYPE> TOK
        SymbolEntry <TYPE> TOK2
      CodeProps destructor { $%; @%; } <*> exp TOK
      InitialAction { $%; @%; }
      CodeProps printer { $%; @%; } <*> exp TOK
      Prologue { $ @ }
      %%
      Rule exp
        Alternative
          SymbolItem TOK
          Action { $%; @%; $$ = $1; }
        Alternative
          SymbolItem 'a'
          Action { $<->1; $$ = 1; }
        Alternative
          SymbolItem 'b'
          Action { $<foo->bar>$; }
      %%
      Epilogue {\n$ @ // Should not warn.\n}
      """

  Scenario: tests/input.at "%start after first rule": %start may follow the rules
    Given the grammar file:
      """
      %%
      false_start: %empty;
      start: false_start ;
      %start start;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule false_start
        Alternative
          EmptyItem
      Rule start
        Alternative
          SymbolItem false_start
      Start start
      """

  Scenario Outline: tests/named-refs.at "<test>": the brackets must hold exactly one identifier
    Given the grammar file:
      """
      %%
      start: <reference> bar
        { s = $foo; }
      """
    When the file is parsed
    Then parsing fails at line 2 column <column>

    Examples:
      | test                            | reference                | column |
      | Missing identifiers in brackets | foo[]                    | 12     |
      | Redundant words in brackets     | foo[ a d ]               | 15     |
      | Comments in brackets            | foo[/* comment */]       | 25     |
      | Stray symbols in brackets       | foo[ % /* aaa */ *&-.+ ] | 13     |

  Scenario: tests/named-refs.at "Redundant words in LHS brackets": the same holds for the result of a rule
    Given the grammar file:
      """
      %%
      start[a s]: foo;
      """
    When the file is parsed
    Then parsing fails at line 2 column 9

  Scenario: tests/named-refs.at "Factored LHS": a named result applies to every alternative
    Given the grammar file:
      """
      %%
      start[a]: "foo" | "bar";
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule start [a]
        Alternative
          SymbolItem "foo"
        Alternative
          SymbolItem "bar"
      """
