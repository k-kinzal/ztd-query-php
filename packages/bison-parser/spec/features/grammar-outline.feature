Feature: Outline of a Bison Grammar
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Outline of a
  Bison Grammar" with its subsections "The prologue", "Prologue Alternatives",
  "The Bison Declarations Section", "The Grammar Rules Section" and
  "The epilogue".

  A grammar file has four sections: the prologue between %{ and %}, the Bison
  declarations, the grammar rules after the first %%, and the epilogue after
  the second %%.

  Scenario: The four sections appear in order with their delimiters
    Given the grammar file:
      """
      %{
        #include <stdio.h>
      %}

      %token NUM

      %%
      exp: NUM;
      %%

      int main (void) { return yyparse (); }
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n  #include <stdio.h>\n}
      SymbolDeclaration token
        SymbolEntry NUM
      %%
      Rule exp
        Alternative
          SymbolItem NUM
      %%
      Epilogue {\n\nint main (void) { return yyparse (); }\n}
      """

  Scenario: Comments enclosed in /* and */ may appear in any section
    Given the grammar file:
      """
      /* before the declarations */
      %token /* between the directive and its symbol */ NUM
      %% /* after the separator */
      exp: /* before the components */ NUM /* after them */ ;
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %token NUM
      %%
      exp: NUM;
      """

  Scenario: As a GNU extension, // introduces a comment that lasts to the end of the line
    Given the grammar file:
      """
      // before the declarations
      %token NUM // after a declaration
      %% // after the separator
      exp: NUM // after the components
      ;
      """
    When the file is parsed
    Then the tree is the same as for:
      """
      %token NUM
      %%
      exp: NUM;
      """

  Scenario: The prologue between %{ and %} is kept verbatim
    Given the grammar file:
      """
      %{
        #define YYSTYPE double
        #include "calc.h"
      %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n  #define YYSTYPE double\n  #include "calc.h"\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: The %{ and %} delimiters may be omitted when no C declarations are needed
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

  Scenario: The prologue ends at the first %} outside a comment, a string literal, or a character constant
    Given the grammar file:
      """
      %{
        /* not the end: %} */
        const char *s = "%}";
        int c = '%}';
      %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n  /* not the end: %} */\n  const char *s = "%}";\n  int c = '%}';\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: Several prologues may be intermixed with the Bison declarations, in the order written
    Given the grammar file:
      """
      %{
        #include "ptypes.h"
      %}

      %union {
        long n;
        tree t;
      }

      %{
        static void print_token (yytoken_kind_t token, YYSTYPE val);
      %}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Prologue {\n  #include "ptypes.h"\n}
      UnionDeclaration {\n  long n;\n  tree t;\n}
      Prologue {\n  static void print_token (yytoken_kind_t token, YYSTYPE val);\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: %code takes an optional qualifier naming where the code goes
    Given the grammar file:
      """
      %code top {
        #define _GNU_SOURCE
        #include <stdio.h>
      }

      %code requires {
        #include "ptypes.h"
      }

      %code provides {
        void trace_token (yytoken_kind_t token, YYLTYPE loc);
      }

      %code {
        static void print_token (FILE *file, int token, YYSTYPE val);
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code top {\n  #define _GNU_SOURCE\n  #include <stdio.h>\n}
      Code requires {\n  #include "ptypes.h"\n}
      Code provides {\n  void trace_token (yytoken_kind_t token, YYLTYPE loc);\n}
      Code {\n  static void print_token (FILE *file, int token, YYSTYPE val);\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: Bison does not require the %code directives to be written in any particular order
    Given the grammar file:
      """
      %code { static int counter; }
      %code provides { int count (void); }
      %code top { #include <stdlib.h> }
      %code requires { typedef int value; }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code { static int counter; }
      Code provides { int count (void); }
      Code top { #include <stdlib.h> }
      Code requires { typedef int value; }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: A %code directive may be repeated, and each occurrence is kept in declaration order
    Given the grammar file:
      """
      %code requires { #include "type1.h" }
      %union { type1 field1; }
      %code requires { #include "type2.h" }
      %union { type2 field2; }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code requires { #include "type1.h" }
      UnionDeclaration { type1 field1; }
      Code requires { #include "type2.h" }
      UnionDeclaration { type2 field2; }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: Directive groups may be placed in the rules section, each terminated by a semicolon
    Given the grammar file:
      """
      %%
      exp: 'a';
      %code requires { #include "type1.h" };
      %union { type1 field1; };
      %destructor { type1_free ($$); } <field1>;
      %printer { type1_print (yyo, $$); } <field1>;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      Code requires { #include "type1.h" }
      UnionDeclaration { type1 field1; }
      CodeProps destructor { type1_free ($$); } <field1>
      CodeProps printer { type1_print (yyo, $$); } <field1>
      """

  Scenario: A directive in the rules section without its terminating semicolon is an error
    Given the grammar file:
      """
      %%
      exp: 'a';
      %code requires { #include "type1.h" }
      other: 'b';
      """
    When the file is parsed
    Then parsing fails at line 4 column 1

  Scenario: The Bison declarations section may be empty
    Given the grammar file:
      """
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: The first %% may never be omitted, even when it is the first thing in the file
    Given the grammar file:
      """
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 1 column 1

  Scenario: The first %% may not be omitted after declarations either
    Given the grammar file:
      """
      %token NUM
      exp: NUM;
      """
    When the file is parsed
    Then parsing fails at line 2 column 1

  Scenario: There must always be at least one grammar rule
    Given the grammar file:
      """
      %token NUM
      %%
      """
    When the file is parsed
    Then parsing fails at line 3 column 1

  Scenario: Declarations alone do not make a grammar rules section
    Given the grammar file:
      """
      %%
      %code { int x; };
      """
    When the file is parsed
    Then parsing fails at line 3 column 1

  Scenario: The epilogue is copied verbatim, so nothing after the second %% is interpreted
    Given the grammar file:
      """
      %%
      exp: 'a';
      %%
      /* %% and %{ are plain text here */
      int yylex (void) { return 0; }
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      %%
      Epilogue {\n/* %% and %{ are plain text here */\nint yylex (void) { return 0; }\n}
      """

  Scenario: The second %% may be omitted when the epilogue is empty
    Given the grammar file:
      """
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  Scenario: A second %% followed by nothing gives an empty epilogue
    Given the grammar file:
      """
      %%
      exp: 'a';
      %%
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      %%
      Epilogue {\n}
      """
