@manual
Feature: Special Directives
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.4    Special Directives                           manual:4.4
    4.4.1  The %code directive                          manual:4.4.1
    4.4.2  The %default_destructor directive            manual:4.4.2
    4.4.3  The %default_type directive                  manual:4.4.3
    4.4.4  The %destructor directive                    manual:4.4.4
    4.4.5  The %extra_argument directive                manual:4.4.5
    4.4.6  The %extra_context directive                 manual:4.4.6
    4.4.7  The %fallback directive                      manual:4.4.7
    4.4.9  The %include directive                       manual:4.4.9
    4.4.11 The %name directive                          manual:4.4.11
    4.4.13 The %parse_accept directive                  manual:4.4.13
    4.4.14 The %parse_failure directive                 manual:4.4.14
    4.4.16 The %stack_overflow directive                manual:4.4.16
    4.4.17 The %stack_size directive                    manual:4.4.17
    4.4.18 The %start_symbol directive                  manual:4.4.18
    4.4.20 The %token directive                         manual:4.4.20
    4.4.22 The %token_destructor directive              manual:4.4.22
    4.4.23 The %token_prefix directive                  manual:4.4.23
    4.4.24 The %token_type and %type directives         manual:4.4.24
    4.4.25 The %wildcard directive                      manual:4.4.25
    4.4.26 The %realloc and %free directives            manual:4.4.26
  Section 4.4.8, the %if directive and its friends, has its own feature, and
  4.4.10, 4.4.12 and 4.4.15, the precedence directives, are in the precedence
  feature. Section 4.4.21 says %token_class is undocumented; its syntax is
  stated in the feature taken from lemon.c.

  @manual:4.4
  Scenario: Directives may be put before the rules, after the rules, or in their midst
    Given the grammar file:
      """
      %token_prefix TK_
      expr ::= expr PLUS expr.
      %include { #include <stdio.h> }
      expr ::= VALUE.
      %name Calc
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_prefix TK_
      Rule expr
        Item expr
        Item PLUS
        Item expr
      Directive include { #include <stdio.h> }
      Rule expr
        Item VALUE
      Directive name Calc
      """

  @manual:4.4
  Scenario: Lemon supports a fixed list of directives, so any other keyword is an error
    Given the grammar file:
      """
      %frobnicate {x}
      """
    When the file is parsed
    Then parsing fails at line 1 column 2

  @manual:4.4.1
  Scenario: %code gives C code to add at the end of the output, and there may be several
    Given the grammar file:
      """
      %code { int main(void) { return 0; } }
      %code {
        void helper(void) {}
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive code { int main(void) { return 0; } }
      Directive code {\n  void helper(void) {}\n}
      """

  @manual:4.4.2
  Scenario: %default_destructor gives the destructor of the nonterminals that have none of their own
    Given the grammar file:
      """
      %default_destructor { free($$); }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive default_destructor { free($$); }
      """

  @manual:4.4.3
  Scenario: %default_type gives the data type of the nonterminals that have none of their own
    Given the grammar file:
      """
      %default_type {Node*}
      """
    When the file is parsed
    Then the tree is:
      """
      Directive default_type {Node*}
      """

  @manual:4.4.4
  Scenario: %destructor names a nonterminal and gives the C code that disposes of its value
    Given the grammar file:
      """
      %type nt {void*}
      %destructor nt { free($$); }
      nt(A) ::= ID NUM.   { A = malloc( 100 ); }
      """
    When the file is parsed
    Then the tree is:
      """
      TypeDeclaration nt {void*}
      Destructor nt { free($$); }
      Rule nt(A)
        Item ID
        Item NUM
        Code { A = malloc( 100 ); }
      """

  @manual:4.4.5
  Scenario: %extra_argument gives the declaration of the extra parameter of Parse()
    Given the grammar file:
      """
      %extra_argument { MyStruct *pAbc }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive extra_argument { MyStruct *pAbc }
      """

  @manual:4.4.6
  Scenario: %extra_context gives the declaration of the extra parameter of ParseAlloc() and ParseInit()
    Given the grammar file:
      """
      %extra_context { MyStruct *pAbc }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive extra_context { MyStruct *pAbc }
      """

  @manual:4.4.7
  Scenario: %fallback names the fallback token and then the tokens that fall back to it, up to a period
    Given the grammar file:
      """
      %fallback ID ABORT AFTER ASC.
      """
    When the file is parsed
    Then the tree is:
      """
      Fallback ID ABORT AFTER ASC
      """

  @manual:4.4.7
  Scenario: The list of %fallback may span lines up to its period
    Given the grammar file:
      """
      %fallback ID
        ABORT AFTER
        ASC
        .
      """
    When the file is parsed
    Then the tree is:
      """
      Fallback ID ABORT AFTER ASC
      """

  @manual:4.4.7
  Scenario: Every name after %fallback must be a token
    Given the grammar file:
      """
      %fallback ID expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 14

  @manual:4.4.9
  Scenario: %include gives C code for the top of the parser, and several are kept in order
    Given the grammar file:
      """
      %include {#include <unistd.h>}
      %include {#include "tokens.h"}
      """
    When the file is parsed
    Then the tree is:
      """
      Directive include {#include <unistd.h>}
      Directive include {#include "tokens.h"}
      """

  @manual:4.4.11
  Scenario: %name gives the string that replaces "Parse" in the generated names
    Given the grammar file:
      """
      %name Abcde
      """
    When the file is parsed
    Then the tree is:
      """
      Directive name Abcde
      """

  @manual:4.4.13
  Scenario: %parse_accept gives the C code run when the parser accepts its input
    Given the grammar file:
      """
      %parse_accept {
        printf("parsing complete!\n");
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive parse_accept {\n  printf("parsing complete!\\n");\n}
      """

  @manual:4.4.14
  Scenario: %parse_failure gives the C code run when the parser fails to complete
    Given the grammar file:
      """
      %parse_failure {
        fprintf(stderr,"Giving up.  Parser is hopelessly lost...\n");
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive parse_failure {\n  fprintf(stderr,"Giving up.  Parser is hopelessly lost...\\n");\n}
      """

  @manual:4.4.16
  Scenario: %stack_overflow gives the C code run when the parser stack overflows
    Given the grammar file:
      """
      %stack_overflow {
        fprintf(stderr,"Giving up.  Parser stack overflow\n");
      }
      list ::= list element.      // left-recursion.  Good!
      list ::= .
      """
    When the file is parsed
    Then the tree is:
      """
      Directive stack_overflow {\n  fprintf(stderr,"Giving up.  Parser stack overflow\\n");\n}
      Rule list
        Item list
        Item element
      Rule list
      """

  @manual:4.4.17
  Scenario: %stack_size takes a positive integer
    Given the grammar file:
      """
      %stack_size 2000
      """
    When the file is parsed
    Then the tree is:
      """
      Directive stack_size 2000
      """

  @manual:4.4.18
  Scenario: %start_symbol names the start symbol instead of the first nonterminal of the file
    Given the grammar file:
      """
      %start_symbol  prog
      expr ::= VALUE.
      prog ::= expr.
      """
    When the file is parsed
    Then the tree is:
      """
      Directive start_symbol prog
      Rule expr
        Item VALUE
      Rule prog
        Item expr
      """

  @manual:4.4.20
  Scenario: %token declares tokens in advance, zero or more of them, up to a period
    Given the grammar file:
      """
      %token ONE TWO THREE.
      %token .
      expr ::= ONE.
      """
    When the file is parsed
    Then the tree is:
      """
      TokenDeclaration ONE TWO THREE
      TokenDeclaration
      Rule expr
        Item ONE
      """

  @manual:4.4.20
  Scenario: Every name after %token must be a token
    Given the grammar file:
      """
      %token expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:4.4.22
  Scenario: %token_destructor gives the destructor shared by all terminals
    Given the grammar file:
      """
      %token_destructor { free_token($$); }
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_destructor { free_token($$); }
      """

  @manual:4.4.23
  Scenario: %token_prefix gives the prefix of the generated token constants
    Given the grammar file:
      """
      %token_prefix    TOKEN_
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_prefix TOKEN_
      """

  @manual:4.4.24
  Scenario: %token_type gives the data type shared by all terminals, and %type the type of one nonterminal
    Given the grammar file:
      """
      %token_type    {Token*}
      %type   expr  {Expr*}
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_type {Token*}
      TypeDeclaration expr {Expr*}
      """

  @manual:4.4.25
  Scenario: %wildcard names a single token, followed by a period
    Given the grammar file:
      """
      %wildcard ANY.
      """
    When the file is parsed
    Then the tree is:
      """
      Wildcard ANY
      """

  @manual:4.4.25
  Scenario: The wildcard must be a token
    Given the grammar file:
      """
      %wildcard expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 11

  @manual:4.4.26
  Scenario: %realloc and %free name the functions that allocate and free stack space
    Given the grammar file:
      """
      %realloc parserRealloc
      %free parserFree
      """
    When the file is parsed
    Then the tree is:
      """
      Directive realloc parserRealloc
      Directive free parserFree
      """
