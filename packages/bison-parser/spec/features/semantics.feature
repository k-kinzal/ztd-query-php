@manual
Feature: Defining Language Semantics
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.4.1    Data Types of Semantic Values                manual:Value-Type
    3.4.3    Generating the Semantic Value Type           manual:Type-Generation
    3.4.4    The Union Declaration                        manual:Union-Decl
    3.4.5    Providing a Structured Semantic Value Type   manual:Structured-Value-Type
    3.4.6    Actions                                      manual:Actions
    3.4.7    Data Types of Values in Actions              manual:Action-Types
    3.4.8.1  Using Midrule Actions                        manual:Using-Midrule-Actions
    3.4.8.2  Typed Midrule Actions                        manual:Typed-Midrule-Actions

  The parser keeps the code of actions verbatim: what the value and location
  references ($$, $n, $<tag>n, @$, @n) mean is decided by Bison when it
  copies the actions, not by the reader of the grammar file.

  @manual:Value-Type
  Scenario: The value type is set with %define api.value.type and a braced type name
    Given the grammar file:
      """
      %define api.value.type {double}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.value.type = {double}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Value-Type
  Scenario: The braced type name may be several words
    Given the grammar file:
      """
      %define api.value.type {struct semantic_value_type}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.value.type = {struct semantic_value_type}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Type-Generation
  Scenario: With api.value.type union, the tags of %token, %nterm and %type are genuine types
    Given the grammar file:
      """
      %define api.value.type union
      %token <int> INT "integer"
      %token <int> 'n'
      %nterm <int> expr
      %token <char const *> ID "identifier"
      %%
      expr: INT;
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.value.type = union
      SymbolDeclaration token
        SymbolEntry <int> INT "integer"
      SymbolDeclaration token
        SymbolEntry <int> 'n'
      SymbolDeclaration nterm
        SymbolEntry <int> expr
      SymbolDeclaration token
        SymbolEntry <char const *> ID "identifier"
      %%
      Rule expr
        Alternative
          SymbolItem INT
      """

  @manual:Union-Decl
  Scenario: %union is followed by braced code holding the members of the union
    Given the grammar file:
      """
      %union {
        double val;
        symrec *tptr;
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration {\n  double val;\n  symrec *tptr;\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Union-Decl
  Scenario: As an extension to POSIX, a tag is allowed after %union
    Given the grammar file:
      """
      %union value {
        double val;
        symrec *tptr;
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration value {\n  double val;\n  symrec *tptr;\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Union-Decl
  Scenario: As another extension to POSIX, several %union declarations may be given
    Given the grammar file:
      """
      %union { char *string; }
      %token <string> STRING
      %union { char character; }
      %token <character> CHR
      %%
      exp: STRING CHR;
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration { char *string; }
      SymbolDeclaration token
        SymbolEntry <string> STRING
      UnionDeclaration { char character; }
      SymbolDeclaration token
        SymbolEntry <character> CHR
      %%
      Rule exp
        Alternative
          SymbolItem STRING
          SymbolItem CHR
      """

  @manual:Union-Decl
  Scenario: No semicolon is needed after the closing brace of %union
    Given the grammar file:
      """
      %union { int n; }
      %token <n> NUM
      %%
      exp: NUM;
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration { int n; }
      SymbolDeclaration token
        SymbolEntry <n> NUM
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      """

  @manual:Structured-Value-Type
  Scenario: A structured value type of your own is named with api.value.type and used through tags
    Given the grammar file:
      """
      %{
      #include "parser.h"
      %}
      %define api.value.type {union YYSTYPE}
      %nterm <val> expr
      %token <tptr> ID
      %%
      expr: ID;
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n#include "parser.h"\n}
      Define api.value.type = {union YYSTYPE}
      SymbolDeclaration nterm
        SymbolEntry <val> expr
      SymbolDeclaration token
        SymbolEntry <tptr> ID
      %%
      Rule expr
        Alternative
          SymbolItem ID
      """

  @manual:Structured-Value-Type
  Scenario: Type tags may be composite, using the . and -> operators
    Given the grammar file:
      """
      %define api.value.type {struct value}
      %token <number.integer> INT
      %nterm <node->children> list
      %%
      list: INT;
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.value.type = {struct value}
      SymbolDeclaration token
        SymbolEntry <number.integer> INT
      SymbolDeclaration nterm
        SymbolEntry <node->children> list
      %%
      Rule list
        Alternative
          SymbolItem INT
      """

  @manual:Actions
  Scenario: An action refers to the component values with $n and to the result with $$
    Given the grammar file:
      """
      %%
      exp: exp '+' exp { $$ = $1 + $3; };
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
          Action { $$ = $1 + $3; }
      """

  @manual:Actions
  Scenario: An action may use named references instead of positions
    Given the grammar file:
      """
      %%
      exp[result]: exp[left] '+' exp[right] { $result = $left + $right; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp [result]
        Alternative
          SymbolItem exp [left]
          SymbolItem '+'
          SymbolItem exp [right]
          Action { $result = $left + $right; }
      """

  @manual:Actions
  Scenario: The vertical bar separates rules, so an action belongs to one alternative only
    Given the grammar file:
      """
      %%
      a-or-b: 'a'|'b'   { a_or_b_found = 1; };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule a-or-b
        Alternative
          SymbolItem 'a'
        Alternative
          SymbolItem 'b'
          Action { a_or_b_found = 1; }
      """

  @manual:Actions
  Scenario: $n with n zero or negative refers to values on the stack before the rule
    Given the grammar file:
      """
      %%
      foo:
        expr bar '+' expr { $$ = $1 + $4; }
      ;
      bar:
        %empty { previous_expr = $0; }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule foo
        Alternative
          SymbolItem expr
          SymbolItem bar
          SymbolItem '+'
          SymbolItem expr
          Action { $$ = $1 + $4; }
      Rule bar
        Alternative
          EmptyItem
          Action { previous_expr = $0; }
      """

  @manual:Action-Types
  Scenario: A value reference may carry its type, as $<tag>n
    Given the grammar file:
      """
      %union {
        int itype;
        double dtype;
      }
      %%
      exp: 'a' { $<dtype>$ = $<itype>1; };
      """
    When the file is parsed
    Then the tree is:
      """
      UnionDeclaration {\n  int itype;\n  double dtype;\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
          Action { $<dtype>$ = $<itype>1; }
      """

  @manual:Using-Midrule-Actions
  Scenario: A midrule action is an action written before the last component
    Given the grammar file:
      """
      %%
      stmt:
        "let" '(' var ')'
          {
            $<context>$ = push_context ();
            declare_variable ($3);
          }
        stmt
          {
            $$ = $6;
            pop_context ($<context>5);
          }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmt
        Alternative
          SymbolItem "let"
          SymbolItem '('
          SymbolItem var
          SymbolItem ')'
          Action {\n      $<context>$ = push_context ();\n      declare_variable ($3);\n    }
          SymbolItem stmt
          Action {\n      $$ = $6;\n      pop_context ($<context>5);\n    }
      """

  @manual:Typed-Midrule-Actions
  Scenario: A midrule action may be given a semantic type by a tag written before its brace
    Given the grammar file:
      """
      %%
      stmt:
        "let" '(' var ')'
          <context>{
            $$ = push_context ();
            declare_variable ($3);
          }
        stmt
          {
            $$ = $6;
            pop_context ($5);
          }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmt
        Alternative
          SymbolItem "let"
          SymbolItem '('
          SymbolItem var
          SymbolItem ')'
          Action <context> {\n      $$ = push_context ();\n      declare_variable ($3);\n    }
          SymbolItem stmt
          Action {\n      $$ = $6;\n      pop_context ($5);\n    }
      """

  @manual:Using-Midrule-Actions
  Scenario: A midrule action may be named with a bracketed name after its closing brace
    Given the grammar file:
      """
      %%
      stmt:
        "let" '(' var ')'
          {
            $<context>let = push_context ();
            declare_variable ($3);
          }[let]
        stmt
          {
            $$ = $6;
            pop_context ($<context>let);
          }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule stmt
        Alternative
          SymbolItem "let"
          SymbolItem '('
          SymbolItem var
          SymbolItem ')'
          Action {\n      $<context>let = push_context ();\n      declare_variable ($3);\n    } [let]
          SymbolItem stmt
          Action {\n      $$ = $6;\n      pop_context ($<context>let);\n    }
      """
