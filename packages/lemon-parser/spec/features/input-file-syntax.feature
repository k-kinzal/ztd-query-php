@manual
Feature: Input File Syntax
  Source: "The Lemon Parser Generator", doc/lemon.html of SQLite version 3.47.2
  (https://sqlite.org/src/raw/doc/lemon.html?ci=version-3.47.2, published at
  https://sqlite.org/lemon.html). Every scenario is tagged with the section of
  the manual it states, as "manual:" followed by the section number.
  The sections stated in this feature:
    4.0    Input File Syntax                            manual:4.0
    4.1    Terminals and Nonterminals                   manual:4.1

  A grammar file is free format: it has no sections, any declaration may
  occur at any point, whitespace only separates tokens, and comments follow
  the conventions of C and C++.

  @manual:4.0
  Scenario: Declarations may occur at any point in the file, before, among and after the rules
    Given the grammar file:
      """
      %token_prefix TK_
      expr ::= expr PLUS expr.
      %left PLUS.
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
      PrecedenceDeclaration left PLUS
      Rule expr
        Item VALUE
      Directive name Calc
      """

  @manual:4.0
  Scenario: Whitespace is ignored except where it separates tokens
    Given the grammar file:
      """
      expr
        ::=
          expr   PLUS
      expr   .
      %left    PLUS   .
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      expr ::= expr PLUS expr.
      %left PLUS.
      """

  @manual:4.0
  Scenario: Whitespace is needed only to separate tokens, so punctuation needs none
    Given the grammar file:
      """
      expr(A)::=expr(B)PLUS expr(C).{A=B+C;}
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr(A)
        Item expr(B)
        Item PLUS
        Item expr(C)
        Code {A=B+C;}
      """

  @manual:4.0
  Scenario: C comments between /* and */ may appear anywhere between tokens
    Given the grammar file:
      """
      /* the expression */
      expr ::= /* left */ expr PLUS /* right */ expr. /* done */
      %left /* the operator */ PLUS.
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      expr ::= expr PLUS expr.
      %left PLUS.
      """

  @manual:4.0
  Scenario: C++ comments from // to the end of the line may appear anywhere between tokens
    Given the grammar file:
      """
      // the expression
      expr ::= expr PLUS expr. // done
      %left PLUS. // the operator
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      expr ::= expr PLUS expr.
      %left PLUS.
      """

  @manual:4.0
  Scenario: A comment inside braced code is part of the code, not a comment of the grammar
    Given the grammar file:
      """
      expr ::= VALUE. { /* keep } this */ done(); // and } this
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        Code { /* keep } this */ done(); // and } this\n}
      """

  @manual:4.1
  Scenario: A terminal is a string of alphanumeric and underscore characters beginning with an uppercase letter
    Given the grammar file:
      """
      expr ::= PLUS Minus TOKEN_1 T9.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item PLUS
        Item Minus
        Item TOKEN_1
        Item T9
      """

  @manual:4.1
  Scenario: A nonterminal is a string of alphanumeric and underscore characters beginning with a lowercase letter
    Given the grammar file:
      """
      expr ::= term_1 cmdList e9.
      term_1 ::= VALUE.
      cmdList ::= VALUE.
      e9 ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item term_1
        Item cmdList
        Item e9
      Rule term_1
        Item VALUE
      Rule cmdList
        Item VALUE
      Rule e9
        Item VALUE
      """

  @manual:4.1
  Scenario: Terminals and nonterminals need no declaration; the case of the first character tells them apart
    Given the grammar file:
      """
      expr ::= expr PLUS expr.
      expr ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item expr
        Item PLUS
        Item expr
      Rule expr
        Item VALUE
      """

  @manual:4.1
  Scenario: A terminal in single quotes, as yacc allows, is not a symbol
    Given the grammar file:
      """
      expr ::= LPAREN expr ')'.
      """
    When the file is parsed
    Then parsing fails at line 1 column 22

  @manual:4.1
  Scenario: The left-hand side of a rule must be a nonterminal, so an uppercase name cannot start a rule
    Given the grammar file:
      """
      EXPR ::= VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
