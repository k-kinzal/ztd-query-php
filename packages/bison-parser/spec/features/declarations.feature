@manual
Feature: Bison Declarations
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.7.1    Require a Version of Bison                   manual:Require-Decl
    3.7.2    Token Kind Names                             manual:Token-Decl
    3.7.3    Operator Precedence                          manual:Precedence-Decl
    3.7.4    Nonterminal Symbols                          manual:Type-Decl
    3.7.5    Syntax of Symbol Declarations                manual:Symbol-Decls
    3.7.6    Performing Actions before Parsing            manual:Initial-Action-Decl
    3.7.7    Freeing Discarded Symbols                    manual:Destructor-Decl
    3.7.8    Printing Semantic Values                     manual:Printer-Decl
    3.7.9    Suppressing Conflict Warnings                manual:Expect-Decl
    3.7.10   The Start-Symbol                             manual:Start-Decl
    3.7.11   A Pure (Reentrant) Parser                    manual:Pure-Decl
    3.7.12   A Push Parser                                manual:Push-Decl
    Chapter 4, Parser C-Language Interface
    4.6.2    Token Internationalization                   manual:Token-I18n

  One reading is recorded here because the manual and Bison's own grammar of
  grammar files differ: the example %left OR 134 "<=" 135 in "Operator
  Precedence" gives a string token a number, which parse-gram.y of Bison
  3.8.2 does not accept after a string. The manual is followed.

  @manual:Require-Decl
  Scenario: %require names the minimum version of Bison as a string
    Given the grammar file:
      """
      %require "3.2"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Option require "3.2"
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Token-Decl
  Scenario: %token declares a token kind name
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

  @manual:Token-Decl
  Scenario: The numeric code of a token kind is a nonnegative decimal or hexadecimal integer after its name
    Given the grammar file:
      """
      %token NUM 300
      %token XNUM 0x12d // a GNU extension
      %%
      exp: NUM XNUM;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry NUM 300
      SymbolDeclaration token
        SymbolEntry XNUM 301
      %%
      Rule exp
        Alternative
          SymbolItem NUM
          SymbolItem XNUM
      """

  @manual:Token-Decl
  Scenario: A token declaration may carry the data type alternative in angle brackets
    Given the grammar file:
      """
      %union {              /* define stack type */
        double val;
        symrec *tptr;
      }
      %token <val> NUM      /* define token NUM and its type */
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration {              /* define stack type */\n  double val;\n  symrec *tptr;\n}
      SymbolDeclaration token
        SymbolEntry <val> NUM
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Token-Decl
  Scenario: A literal string written at the end of a %token declaration is an alias of the name
    Given the grammar file:
      """
      %token ARROW "=>"
      %%
      exp: ARROW;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry ARROW "=>"
      %%
      Rule exp
        Alternative
          SymbolItem ARROW
      """

  @manual:Token-Decl
  Scenario: Tag, name, code and alias may all be given, and a precedence declaration may then list the alias
    Given the grammar file:
      """
      %token  <operator>  OR      "||"
      %token  <operator>  LE 134  "<="
      %left  OR  "<="
      %%
      exp: exp OR exp | exp "<=" exp;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry <operator> OR "||"
      SymbolDeclaration token
        SymbolEntry <operator> LE 134 "<="
      PrecedenceDeclaration left
        SymbolEntry OR
        SymbolEntry "<="
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem OR
          SymbolItem exp
        Alternative
          SymbolItem exp
          SymbolItem "<="
          SymbolItem exp
      """

  @manual:Token-Decl
  Scenario: String aliases may be marked for internationalization with _("...")
    Given the grammar file:
      """
      %token
          OR     "||"
          LPAREN "("
          RPAREN ")"
          '\n'   _("end of line")
        <double>
          NUM    _("number")
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry OR "||"
        SymbolEntry LPAREN "("
        SymbolEntry RPAREN ")"
        SymbolEntry '\n' _("end of line")
        SymbolEntry <double> NUM _("number")
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Token-I18n
  Scenario: The end-of-file token may be renamed by declaring a token with code 0
    Given the grammar file:
      """
      %token END 0 _("end of input")
      %%
      exp: END;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry END 0 _("end of input")
      %%
      Rule exp
        Alternative
          SymbolItem END
      """

  @manual:Precedence-Decl
  Scenario: %left, %right, %nonassoc and %precedence declare tokens with associativity and precedence
    Given the grammar file:
      """
      %left '+' '-'
      %right '^'
      %nonassoc '<' '>'
      %precedence THEN ELSE
      %%
      exp: exp '+' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
        SymbolEntry '+'
        SymbolEntry '-'
      PrecedenceDeclaration right
        SymbolEntry '^'
      PrecedenceDeclaration nonassoc
        SymbolEntry '<'
        SymbolEntry '>'
      PrecedenceDeclaration precedence
        SymbolEntry THEN
        SymbolEntry ELSE
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
      """

  @manual:Precedence-Decl
  Scenario: A precedence declaration may carry a type like %token
    Given the grammar file:
      """
      %left <operator> '+' '-'
      %%
      exp: exp '+' exp;
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
        SymbolEntry <operator> '+'
        SymbolEntry <operator> '-'
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '+'
          SymbolItem exp
      """

  @manual:Precedence-Decl
  Scenario: In a precedence declaration a literal string is a separate token, never an alias
    Given the grammar file:
      """
      %left  OR "<="         // Does not declare an alias.
      %left  OR 134 "<=" 135 // Declares 134 for OR and 135 for "<=".
      %%
      exp: exp OR exp;
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
        SymbolEntry OR
        SymbolEntry "<="
      PrecedenceDeclaration left
        SymbolEntry OR 134
        SymbolEntry "<=" 135
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem OR
          SymbolItem exp
      """

  @manual:Type-Decl
  Scenario: %type declares the value type of nonterminal symbols, several at a time
    Given the grammar file:
      """
      %type <val> exp term factor
      %%
      exp: term;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration type
        SymbolEntry <val> exp
        SymbolEntry <val> term
        SymbolEntry <val> factor
      %%
      Rule exp
        Alternative
          SymbolItem term
      """

  @manual:Type-Decl
  Scenario: Bison accepts %type on terminal symbols as well
    Given the grammar file:
      """
      %type <val> NUM '+' "<="
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration type
        SymbolEntry <val> NUM
        SymbolEntry <val> '+'
        SymbolEntry <val> "<="
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Type-Decl
  Scenario: %nterm declares exclusively nonterminal symbols
    Given the grammar file:
      """
      %nterm <val> exp term
      %%
      exp: term;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration nterm
        SymbolEntry <val> exp
        SymbolEntry <val> term
      %%
      Rule exp
        Alternative
          SymbolItem term
      """

  @manual:Symbol-Decls
  Scenario: %token takes an optional tag, then one or more names each with an optional number and string, repeated per tag
    Given the grammar file:
      """
      %token <a> X 1 "x" Y <b> Z "z" W 4
      %%
      exp: X;
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration token
        SymbolEntry <a> X 1 "x"
        SymbolEntry <a> Y
        SymbolEntry <b> Z "z"
        SymbolEntry <b> W 4
      %%
      Rule exp
        Alternative
          SymbolItem X
      """

  @manual:Symbol-Decls
  Scenario: %left takes an optional tag, then one or more names each with an optional number, repeated per tag
    Given the grammar file:
      """
      %left <a> X 1 Y <b> Z 2
      %%
      exp: X;
      """
    When the file is parsed
    Then the tree is:
      """
      PrecedenceDeclaration left
        SymbolEntry <a> X 1
        SymbolEntry <a> Y
        SymbolEntry <b> Z 2
      %%
      Rule exp
        Alternative
          SymbolItem X
      """

  @manual:Symbol-Decls
  Scenario: %type takes an optional tag, then one or more identifiers, characters or strings, repeated per tag
    Given the grammar file:
      """
      %type <a> x 'c' "str" <b> y
      %%
      x: 'c';
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration type
        SymbolEntry <a> x
        SymbolEntry <a> 'c'
        SymbolEntry <a> "str"
        SymbolEntry <b> y
      %%
      Rule x
        Alternative
          SymbolItem 'c'
      """

  @manual:Symbol-Decls
  Scenario: %nterm takes an optional tag, then one or more identifiers, repeated per tag
    Given the grammar file:
      """
      %nterm <a> x y <b> z
      %%
      x: 'c';
      """
    When the file is parsed
    Then the tree is:
      """
      SymbolDeclaration nterm
        SymbolEntry <a> x
        SymbolEntry <a> y
        SymbolEntry <b> z
      %%
      Rule x
        Alternative
          SymbolItem 'c'
      """

  @manual:Symbol-Decls
  Scenario: A %token entry starts with an identifier or a character, so a string cannot start one
    Given the grammar file:
      """
      %token "str"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:Symbol-Decls
  Scenario: A %token entry takes at most one string, so a second string cannot start the next entry
    Given the grammar file:
      """
      %token X "a" "b"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 14

  @manual:Symbol-Decls
  Scenario: %nterm takes identifiers only, not characters
    Given the grammar file:
      """
      %nterm 'c'
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:Symbol-Decls
  Scenario: %nterm takes identifiers only, not strings
    Given the grammar file:
      """
      %nterm "str"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 8

  @manual:Symbol-Decls
  Scenario: %nterm identifiers take no number
    Given the grammar file:
      """
      %nterm x 1
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 10

  @manual:Symbol-Decls
  Scenario: %nterm identifiers take no string alias
    Given the grammar file:
      """
      %nterm x "alias"
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 10

  @manual:Symbol-Decls
  Scenario: %type symbols take no number
    Given the grammar file:
      """
      %type <t> x 1
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 13

  @manual:Symbol-Decls
  Scenario: A tag must be followed by at least one symbol
    Given the grammar file:
      """
      %token <t>
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Symbol-Decls
  Scenario: A symbol declaration must declare at least one symbol
    Given the grammar file:
      """
      %token
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Initial-Action-Decl
  Scenario: %initial-action holds braced code run before parsing, and may use the %parse-param
    Given the grammar file:
      """
      %parse-param { char const *file_name };
      %initial-action
      {
        @$.initialize (file_name);
      };
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Param parse-param { char const *file_name }
      InitialAction {\n  @$.initialize (file_name);\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Destructor-Decl
  Scenario: %destructor takes braced code and the symbols or type tags it applies to, including <*> and <>
    Given the grammar file:
      """
      %union { char *string; }
      %token <string> STRING1 STRING2
      %nterm <string> string1 string2
      %union { char character; }
      %token <character> CHR
      %nterm <character> chr
      %token TAGLESS

      %destructor { } <character>
      %destructor { free ($$); } <*>
      %destructor { free ($$); printf ("%d", @$.first_line); } STRING1 string1
      %destructor { printf ("Discarding tagless symbol.\n"); } <>
      %%
      string1: STRING1;
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration { char *string; }
      SymbolDeclaration token
        SymbolEntry <string> STRING1
        SymbolEntry <string> STRING2
      SymbolDeclaration nterm
        SymbolEntry <string> string1
        SymbolEntry <string> string2
      UnionDeclaration { char character; }
      SymbolDeclaration token
        SymbolEntry <character> CHR
      SymbolDeclaration nterm
        SymbolEntry <character> chr
      SymbolDeclaration token
        SymbolEntry TAGLESS
      CodeProps destructor { } <character>
      CodeProps destructor { free ($$); } <*>
      CodeProps destructor { free ($$); printf ("%d", @$.first_line); } STRING1 string1
      CodeProps destructor { printf ("Discarding tagless symbol.\\n"); } <>
      %%
      Rule string1
        Alternative
          SymbolItem STRING1
      """

  @manual:Printer-Decl
  Scenario: %printer has the same syntax as %destructor
    Given the grammar file:
      """
      %printer { fprintf (yyo, "'%c'", $$); } <character>
      %printer { fprintf (yyo, "&%p", $$); } <*>
      %printer { fprintf (yyo, "\"%s\"", $$); } STRING1 string1
      %printer { fprintf (yyo, "<>"); } <>
      %%
      string1: STRING1;
      """
    When the file is parsed
    Then the tree is:
      """
      CodeProps printer { fprintf (yyo, "'%c'", $$); } <character>
      CodeProps printer { fprintf (yyo, "&%p", $$); } <*>
      CodeProps printer { fprintf (yyo, "\\"%s\\"", $$); } STRING1 string1
      CodeProps printer { fprintf (yyo, "<>"); } <>
      %%
      Rule string1
        Alternative
          SymbolItem STRING1
      """

  @manual:Printer-Decl
  Scenario: The symbols of %destructor and %printer may be identifiers, characters, strings and type tags
    Given the grammar file:
      """
      %destructor { free ($$); } exp NUM '+' "float" <ival> <*> <>
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      CodeProps destructor { free ($$); } exp NUM '+' "float" <ival> <*> <>
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Destructor-Decl
  Scenario: %destructor requires at least one symbol or tag
    Given the grammar file:
      """
      %destructor { free ($$); }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Expect-Decl
  Scenario: %expect declares the expected number of shift/reduce conflicts as a decimal integer
    Given the grammar file:
      """
      %expect 1
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Expect 1
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Expect-Decl
  Scenario: %expect-rr declares the expected number of reduce/reduce conflicts of a GLR parser
    Given the grammar file:
      """
      %glr-parser
      %expect-rr 2
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      Expect reduce/reduce 2
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Expect-Decl
  Scenario: %expect requires a number
    Given the grammar file:
      """
      %expect
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Expect-Decl
  Scenario: %expect and %expect-rr may also be attached to individual rules, after the components
    Given the grammar file:
      """
      %%
      dims:
        empty_dims
      | '[' expr ']' dims
      ;

      empty_dims:
        %empty   %expect 2
      | empty_dims '[' ']'
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule dims
        Alternative
          SymbolItem empty_dims
        Alternative
          SymbolItem '['
          SymbolItem expr
          SymbolItem ']'
          SymbolItem dims
      Rule empty_dims
        Alternative
          EmptyItem
          ExpectItem 2
        Alternative
          SymbolItem empty_dims
          SymbolItem '['
          SymbolItem ']'
      """

  @manual:Expect-Decl
  Scenario: To annotate a midrule action, %expect or %expect-rr is written before the action
    Given the grammar file:
      """
      %glr-parser
      %expect-rr 1

      %%

      clause:
        "condition" %expect-rr 1 { value_mode(); } '(' exprs ')'
      | "condition" %expect-rr 1 { class_mode(); } '(' types ')'
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      Flag glr-parser
      Expect reduce/reduce 1
      %%
      Rule clause
        Alternative
          SymbolItem "condition"
          ExpectItem reduce/reduce 1
          Action { value_mode(); }
          SymbolItem '('
          SymbolItem exprs
          SymbolItem ')'
        Alternative
          SymbolItem "condition"
          ExpectItem reduce/reduce 1
          Action { class_mode(); }
          SymbolItem '('
          SymbolItem types
          SymbolItem ')'
      """

  @manual:Start-Decl
  Scenario: %start names the start symbol
    Given the grammar file:
      """
      %start program
      %%
      exp: 'a';
      program: exp;
      """
    When the file is parsed
    Then the tree is:
      """
      Start program
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      Rule program
        Alternative
          SymbolItem exp
      """

  @manual:Start-Decl
  Scenario: %start requires a symbol
    Given the grammar file:
      """
      %start
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  @manual:Pure-Decl
  Scenario: A pure, reentrant parser is requested with %define api.pure full
    Given the grammar file:
      """
      %define api.pure full
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.pure = full
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Push-Decl
  Scenario: A push parser is requested with %define api.push-pull push, usually together with api.pure
    Given the grammar file:
      """
      %define api.pure full
      %define api.push-pull push
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.pure = full
      Define api.push-pull = push
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Push-Decl
  Scenario: Both interfaces are requested with %define api.push-pull both
    Given the grammar file:
      """
      %define api.push-pull both
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.push-pull = both
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """
