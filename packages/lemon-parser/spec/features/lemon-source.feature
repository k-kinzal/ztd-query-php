@source
Feature: Behaviour fixed by lemon.c
  Source: tool/lemon.c of SQLite version 3.47.2
  (https://sqlite.org/src/raw/tool/lemon.c?ci=version-3.47.2), read as the
  reference for what the manual leaves unsaid; it is not run. Every scenario
  is tagged with the function it follows, as "source:" followed by the
  function name, and its title cites the line of that function.
    Parse()              the tokenizer, lines 3112 to 3200          source:Parse
    parseonetoken()      the reading states, lines 2355 to 2894     source:parseonetoken
    preprocess_input()   the %if regions, lines 2998 to 3060        source:preprocess_input
    eval_preprocessor_boolean()  the %if expression, lines 2897 to 2996  source:eval_preprocessor_boolean
  Where lemon.c reports an error, the message is stated too, as Lemon
  words it.

  @source:Parse
  Scenario: line 3132: a string is anything between double quotes, with no escapes, and may be a directive argument
    Given the grammar file:
      """
      %token_prefix "TK_"
      %name "Calc\Parser"
      """
    When the file is parsed
    Then the tree is:
      """
      Directive token_prefix "TK_"
      Directive name "Calc\\Parser"
      """

  @source:Parse
  Scenario: line 3138: a string open at the end of the file is an error
    Given the grammar file:
      """
      %name "Calc
      """
    When the file is parsed
    Then parsing fails at line 1 column 7
    And the error says:
      """
      String starting on this line is not terminated before the end of the file.
      """

  @source:Parse
  Scenario: line 3147: a block of C code ends at the brace that balances its opening one
    Given the grammar file:
      """
      expr ::= VALUE. { if (a) { b(); } else { c(); } }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        Code { if (a) { b(); } else { c(); } }
      """

  @source:Parse
  Scenario: line 3154: braces inside comments, strings and character literals in C code do not count
    Given the grammar file:
      """
      expr ::= VALUE. { a = "}"; b = '}'; c = '\''; d = "\"}"; /* } */ // }
      }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        Code { a = "}"; b = '}'; c = '\\''; d = "\\"}"; /* } */ // }\n}
      """

  @source:Parse
  Scenario: line 3178: C code open at the end of the file is an error
    Given the grammar file:
      """
      expr ::= VALUE. { a = 1;
      """
    When the file is parsed
    Then parsing fails at line 1 column 17
    And the error says:
      """
      C code starting on this line is not terminated before the end of the file.
      """

  @source:Parse
  Scenario: line 3187: an identifier starts with a letter or digit and continues with letters, digits and underscores
    Given the grammar file:
      """
      9x ::= VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
    And the error says:
      """
      Token "9x" should be either "%" or a nonterminal name.
      """

  @source:Parse
  Scenario: line 3193: a bar or slash followed by a letter is one token, which extends the terminal before it
    Given the grammar file:
      """
      expr ::= expr PLUS|MINUS expr.
      expr ::= expr TIMES/DIVIDE/MOD expr.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item expr
        Item PLUS|MINUS
        Item expr
      Rule expr
        Item expr
        Item TIMES|DIVIDE|MOD
        Item expr
      """

  @source:Parse
  Scenario: line 3197: any other character is a token of its own, so a space may follow the %
    Given the grammar file:
      """
      % name Calc
      """
    When the file is parsed
    Then the tree is:
      """
      Directive name Calc
      """

  @source:Parse
  Scenario: line 3122: the slash right after /* is part of the comment, so /*/ does not close what it opened
    Given the grammar file:
      """
      /*/ still a comment */ expr ::= VALUE.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
      """

  @source:Parse
  Scenario: line 3120: a comment open at the end of the file ends the file
    Given the grammar file:
      """
      expr ::= VALUE. /* never closed
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
      """

  @source:parseonetoken
  Scenario: line 2389: {NEVER-REDUCE} after a rule marks it instead of giving it code
    Given the grammar file:
      """
      expr ::= VALUE. {NEVER-REDUCE}
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        NeverReduce
      """

  @source:parseonetoken
  Scenario: line 2378: code and a precedence mark attach to the rule most recently completed, even after declarations
    Given the grammar file:
      """
      expr ::= VALUE.
      %left PLUS.
      [PLUS] { done(); }
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item VALUE
        Precedence PLUS
        Code { done(); }
      PrecedenceDeclaration left PLUS
      """

  @source:parseonetoken
  Scenario: line 2379: code before any rule has nothing to attach to
    Given the grammar file:
      """
      { done(); }
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
    And the error says:
      """
      There is no prior rule upon which to attach the code fragment which begins on this line.
      """

  @source:parseonetoken
  Scenario: line 2384: a rule takes one code fragment only
    Given the grammar file:
      """
      expr ::= VALUE. { a } { b }
      """
    When the file is parsed
    Then parsing fails at line 1 column 23
    And the error says:
      """
      Code fragment beginning on this line is not the first to follow the previous rule.
      """

  @source:parseonetoken
  Scenario: line 2410: a precedence mark before any rule has nothing to attach to
    Given the grammar file:
      """
      [PLUS]
      """
    When the file is parsed
    Then parsing fails at line 1 column 2
    And the error says:
      """
      There is no prior rule to assign precedence "[PLUS]".
      """

  @source:parseonetoken
  Scenario: line 2414: a rule takes one precedence mark only
    Given the grammar file:
      """
      expr ::= VALUE. [A] [B]
      """
    When the file is parsed
    Then parsing fails at line 1 column 22
    And the error says:
      """
      Precedence mark on this line is not the first to follow the previous rule.
      """

  @source:parseonetoken
  Scenario: line 2425: a precedence mark must be closed with ]
    Given the grammar file:
      """
      expr ::= VALUE. [A .
      """
    When the file is parsed
    Then parsing fails at line 1 column 20
    And the error says:
      """
      Missing "]" on precedence mark.
      """

  @source:parseonetoken
  Scenario: line 2438: the left-hand side must be followed by ::= or an alias
    Given the grammar file:
      """
      expr = VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 6
    And the error says:
      """
      Expected to see a ":" following the LHS symbol "expr".
      """

  @source:parseonetoken
  Scenario: line 2450: an alias of the left-hand side must start with a letter
    Given the grammar file:
      """
      expr(1) ::= VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 6
    And the error says:
      """
      "1" is not a valid alias for the LHS "expr"
      """

  @source:parseonetoken
  Scenario: line 2461: an alias of the left-hand side must be closed with )
    Given the grammar file:
      """
      expr(A ::= VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 8
    And the error says:
      """
      Missing ")" following LHS alias name "A".
      """

  @source:parseonetoken
  Scenario: line 2471: ::= must follow the alias of the left-hand side
    Given the grammar file:
      """
      expr(A) = VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 9
    And the error says:
      """
      Missing "->" following: "expr(A)".
      """

  @source:parseonetoken
  Scenario: line 2546: a compound may not contain a nonterminal
    Given the grammar file:
      """
      expr ::= expr|PLUS VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 14
    And the error says:
      """
      Cannot form a compound containing a non-terminal
      """

  @source:parseonetoken
  Scenario: line 2529: a bar followed by a lowercase name is not a compound, so it is illegal on the right-hand side
    Given the grammar file:
      """
      expr ::= expr PLUS|minus expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 19
    And the error says:
      """
      Illegal character on RHS of rule: "|minus".
      """

  @source:parseonetoken
  Scenario: line 2554: any other token on the right-hand side is an error
    Given the grammar file:
      """
      expr ::= VALUE @ VALUE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 16
    And the error says:
      """
      Illegal character on RHS of rule: "@".
      """

  @source:parseonetoken
  Scenario: line 2565: an alias on the right-hand side must start with a letter
    Given the grammar file:
      """
      expr ::= VALUE(1).
      """
    When the file is parsed
    Then parsing fails at line 1 column 16
    And the error says:
      """
      "1" is not a valid alias for the RHS symbol "VALUE"
      """

  @source:parseonetoken
  Scenario: line 2576: an alias on the right-hand side must be closed with ), and Lemon names the left-hand alias in the message
    Given the grammar file:
      """
      expr(A) ::= VALUE(B.
      """
    When the file is parsed
    Then parsing fails at line 1 column 20
    And the error says:
      """
      Missing ")" following LHS alias name "A".
      """

  @source:parseonetoken
  Scenario: line 2667: the keyword after % must start with a letter
    Given the grammar file:
      """
      %1 {x}
      """
    When the file is parsed
    Then parsing fails at line 1 column 2
    And the error says:
      """
      Illegal declaration keyword: "1".
      """

  @source:parseonetoken
  Scenario: line 2661: a keyword Lemon does not know is an error
    Given the grammar file:
      """
      %frobnicate {x}
      """
    When the file is parsed
    Then parsing fails at line 1 column 2
    And the error says:
      """
      Unknown declaration keyword: "%frobnicate".
      """

  @source:parseonetoken
  Scenario: line 2731: the argument of a one-argument directive is braced code, a string, or a word
    Given the grammar file:
      """
      %name Calc
      %token_prefix "TK_"
      %include { int x; }
      %stack_size 2000
      """
    When the file is parsed
    Then the tree is:
      """
      Directive name Calc
      Directive token_prefix "TK_"
      Directive include { int x; }
      Directive stack_size 2000
      """

  @source:parseonetoken
  Scenario: line 2785: any other argument is an error
    Given the grammar file:
      """
      %name (Calc)
      """
    When the file is parsed
    Then parsing fails at line 1 column 7
    And the error says:
      """
      Illegal argument to %name: (
      """

  @source:parseonetoken
  Scenario: line 2675: %destructor needs a symbol name
    Given the grammar file:
      """
      %destructor . { free($$); }
      """
    When the file is parsed
    Then parsing fails at line 1 column 13
    And the error says:
      """
      Symbol name missing after %destructor keyword
      """

  @source:parseonetoken
  Scenario: line 2689: %type needs a symbol name
    Given the grammar file:
      """
      %type { int }
      """
    When the file is parsed
    Then parsing fails at line 1 column 7
    And the error says:
      """
      Symbol name missing after %type keyword
      """

  @source:parseonetoken
  Scenario: line 2696: a symbol takes one %type only
    Given the grammar file:
      """
      %type expr {int}
      %type expr {long}
      """
    When the file is parsed
    Then parsing fails at line 2 column 7
    And the error says:
      """
      Symbol %type "expr" already defined
      """

  @source:parseonetoken
  Scenario: line 2716: a terminal takes one precedence only
    Given the grammar file:
      """
      %left PLUS.
      %right PLUS.
      """
    When the file is parsed
    Then parsing fails at line 2 column 8
    And the error says:
      """
      Symbol "PLUS" has already be given a precedence.
      """

  @source:parseonetoken
  Scenario: line 2725: only a terminal can be given a precedence
    Given the grammar file:
      """
      %left expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 7
    And the error says:
      """
      Can't assign a precedence to "expr".
      """

  @source:parseonetoken
  Scenario: line 2795: every %fallback argument must be a token
    Given the grammar file:
      """
      %fallback ID expr.
      """
    When the file is parsed
    Then parsing fails at line 1 column 14
    And the error says:
      """
      %fallback argument "expr" should be a token
      """

  @source:parseonetoken
  Scenario: line 2803: a token falls back to one token only
    Given the grammar file:
      """
      %fallback ID ABORT.
      %fallback NUM ABORT.
      """
    When the file is parsed
    Then parsing fails at line 2 column 15
    And the error says:
      """
      More than one fallback assigned to token ABORT
      """

  @source:parseonetoken
  Scenario: line 2826: every %token argument must be a token
    Given the grammar file:
      """
      %token ONE two.
      """
    When the file is parsed
    Then parsing fails at line 1 column 12
    And the error says:
      """
      %token argument "two" should be a token
      """

  @source:parseonetoken
  Scenario: line 2836: the %wildcard argument must be a token
    Given the grammar file:
      """
      %wildcard any.
      """
    When the file is parsed
    Then parsing fails at line 1 column 11
    And the error says:
      """
      %wildcard argument "any" should be a token
      """

  @source:parseonetoken
  Scenario: line 2843: there is one wildcard only
    Given the grammar file:
      """
      %wildcard ANY.
      %wildcard OTHER.
      """
    When the file is parsed
    Then parsing fails at line 2 column 11
    And the error says:
      """
      Extra wildcard to token: OTHER
      """

  @source:parseonetoken
  Scenario: line 2848: %wildcard may name no token at all
    Given the grammar file:
      """
      %wildcard .
      """
    When the file is parsed
    Then the tree is:
      """
      Wildcard
      """

  @source:parseonetoken
  Scenario: line 2866: %token_class names a class with a lowercase identifier, then its tokens, joined with bars or slashes, up to a period
    Given the grammar file:
      """
      %token_class id ID|INDEXED|JOIN_KW.
      %token_class number INTEGER FLOAT /BLOB.
      """
    When the file is parsed
    Then the tree is:
      """
      TokenClass id ID INDEXED JOIN_KW
      TokenClass number INTEGER FLOAT BLOB
      """

  @source:parseonetoken
  Scenario: line 2851: the name of a %token_class must be a lowercase identifier
    Given the grammar file:
      """
      %token_class ID A.
      """
    When the file is parsed
    Then parsing fails at line 1 column 14
    And the error says:
      """
      %token_class must be followed by an identifier: ID
      """

  @source:parseonetoken
  Scenario: line 2856: the name of a %token_class must not be a symbol already seen
    Given the grammar file:
      """
      x ::= A.
      %token_class x A.
      """
    When the file is parsed
    Then parsing fails at line 2 column 14
    And the error says:
      """
      Symbol "x" already used
      """

  @source:parseonetoken
  Scenario: line 2872: every member of a %token_class must be a token
    Given the grammar file:
      """
      %token_class id a.
      """
    When the file is parsed
    Then parsing fails at line 1 column 17
    And the error says:
      """
      %token_class argument "a" should be a token
      """

  @source:preprocess_input
  Scenario: line 3014: an excluded region is blanked, not removed, so the lines after it keep their numbers
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      %endif
      EXPR ::= X.
      """
    When the file is parsed
    Then parsing fails at line 4 column 1

  @source:preprocess_input
  Scenario: line 3056: a region open at the end of the file is an error
    Given the grammar file:
      """
      %ifdef MACRO
      expr ::= ONE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 1
    And the error says:
      """
      unterminated %ifdef starting on line 1
      """

  @source:preprocess_input
  Scenario: line 3025: %if, %ifdef and %ifndef are directives only when a space follows the keyword
    Given the grammar file:
      """
      %ifdef
      expr ::= ONE.
      """
    When the file is parsed
    Then parsing fails at line 1 column 2
    And the error says:
      """
      Unknown declaration keyword: "%ifdef".
      """

  @source:preprocess_input
  Scenario: line 3018: %else outside any region opens an excluded region up to the next %endif
    Given the grammar file:
      """
      expr ::= ONE.
      %else
      expr ::= TWO.
      %endif
      expr ::= THREE.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      Rule expr
        Item THREE
      """

  @source:eval_preprocessor_boolean
  Scenario: line 2910: || answers true as soon as its left side is true, whatever follows
    Given the grammar file:
      """
      %if A || B && C
      expr ::= ONE.
      %endif
      """
    And the names defined: A
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      """

  @source:eval_preprocessor_boolean
  Scenario: line 2917: && answers false as soon as its left side is false, whatever follows
    Given the grammar file:
      """
      %if A && B || C
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    And the names defined: C
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item TWO
      """

  @source:eval_preprocessor_boolean
  Scenario: line 2903: ! negates the term that follows it, and two negate back
    Given the grammar file:
      """
      %if !!A
      expr ::= ONE.
      %endif
      %if !A
      expr ::= TWO.
      %endif
      """
    And the names defined: A
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item ONE
      """

  @source:eval_preprocessor_boolean
  Scenario: line 2914: an operator where a term is expected is a syntax error of %if
    Given the grammar file:
      """
      %if && A
      expr ::= ONE.
      %endif
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

  @source:eval_preprocessor_boolean
  Scenario: line 2917: an operator at the end of the expression is not an error, because && already answered
    Given the grammar file:
      """
      %if A &&
      expr ::= ONE.
      %endif
      expr ::= TWO.
      """
    When the file is parsed
    Then the tree is:
      """
      Rule expr
        Item TWO
      """
