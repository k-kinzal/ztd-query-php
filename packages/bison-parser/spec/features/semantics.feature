Feature: Defining Language Semantics
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Defining
  Language Semantics" with its subsections "Data Types of Semantic Values",
  "More Than One Value Type", "Generating the Semantic Value Type", "The Union
  Declaration", "Providing a Structured Semantic Value Type", "Actions", "Data
  Types of Values in Actions" and "Actions in Midrule".

  The parser keeps the code of actions verbatim: what the value and location
  references ($$, $n, $<tag>n, @$, @n) mean is decided by Bison when it
  copies the actions, not by the reader of the grammar file.

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
