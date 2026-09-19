@manual
Feature: Bison Declaration Summary
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.7.13   Bison Declaration Summary                    manual:Decl-Summary

  Every directive the summary lists is recognized in the declarations section.

  @manual:Decl-Summary
  Scenario Outline: <declaration> is a declaration
    Given the grammar file:
      """
      <declaration>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      <node>
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

    Examples: Grammar declarations
      | declaration                          | node                                       |
      | %union { int n; }                    | UnionDeclaration { int n; }                |
      | %default-prec                        | Flag default-prec                          |
      | %start exp                           | Start exp                                  |
      | %expect 1                            | Expect 1                                   |
      | %expect-rr 1                         | Expect reduce/reduce 1                     |
      | %code { int x; }                     | Code { int x; }                            |
      | %code requires { int x; }            | Code requires { int x; }                   |
      | %destructor { free ($$); } <*>       | CodeProps destructor { free ($$); } <*>    |
      | %no-default-prec                     | Flag no-default-prec                       |

    Examples: Output and behaviour
      | declaration                          | node                                       |
      | %debug                               | Flag debug                                 |
      | %define api.pure                     | Define api.pure                            |
      | %define api.pure full                | Define api.pure = full                     |
      | %define api.prefix {c}               | Define api.prefix = {c}                    |
      | %define api.location.file "loc.hh"   | Define api.location.file = "loc.hh"        |
      | %defines                             | Option header spelled %defines             |
      | %defines "parser.h"                  | Option header "parser.h" spelled %defines  |
      | %file-prefix "out"                   | Option file-prefix "out"                   |
      | %header                              | Option header                              |
      | %header "parser.h"                   | Option header "parser.h"                   |
      | %language "C++"                      | Option language "C++"                      |
      | %locations                           | Flag locations                             |
      | %name-prefix "c"                     | Option name-prefix "c"                     |
      | %no-lines                            | Flag no-lines                              |
      | %output "parser.c"                   | Option output "parser.c"                   |
      | %pure-parser                         | Flag pure-parser                           |
      | %require "3.8"                       | Option require "3.8"                       |
      | %skeleton "lalr1.cc"                 | Option skeleton "lalr1.cc"                 |
      | %token-table                         | Flag token-table                           |
      | %verbose                             | Flag verbose                               |
      | %yacc                                | Flag yacc                                  |

  @manual:Decl-Summary
  Scenario Outline: <directive> declares symbols
    Given the grammar file:
      """
      <directive> A B
      %%
      exp: A;
      """
    When the file is parsed
    Then the tree is:
      """
      <node>
        SymbolEntry A
        SymbolEntry B
      %%
      Rule exp
        Alternative
          SymbolItem A
      """

    Examples:
      | directive | node                             |
      | %token    | SymbolDeclaration token          |
      | %nterm    | SymbolDeclaration nterm          |
      | %type     | SymbolDeclaration type           |
      | %right    | PrecedenceDeclaration right      |
      | %left     | PrecedenceDeclaration left       |
      | %nonassoc | PrecedenceDeclaration nonassoc   |

  @manual:Decl-Summary
  Scenario Outline: <directive> requires a string argument
    Given the grammar file:
      """
      <directive>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

    Examples:
      | directive    |
      | %file-prefix |
      | %language    |
      | %name-prefix |
      | %output      |
      | %require     |
      | %skeleton    |
