/* Every declaration Bison accepts, in every spelling it accepts. */
%require "3.0"
%language "C"
%skeleton "yacc.c"
%output "parser.c"
%file-prefix "out"
%file-prefix= "out2"
%name-prefix "pfx"
%name-prefix= "pfx2"
%header
%defines
%define api.value.type {struct value}
%define parse.error verbose
%define api.token.prefix {TOK_}
%define api.prefix {calc}
%define parse.trace
%define api.location.type "loc"
%locations
%debug
%verbose
%token-table
%no-lines
%yacc
%error-verbose
%pure-parser
%pure_parser
%glr-parser
%nondeterministic-parser
%default-prec
%no-default-prec
%expect 3
%expect-rr 2
%expect_rr 0
%initial-action { @$.first_line = 1; }
%param {int *count}
%lex-param {void *scanner}
%parse-param {struct result *result} {int depth}
%union { int ival; char *sval; }
%union values { double dval; }
%start unit
%code { static int helper(void); }
%code requires { #include "types.h" }
%code provides { int helper_public(void); }
%code top { /* top */ }
%code imports { /* imports */ }
%destructor { free($$); } <sval> NAME
%destructor { } <*>
%destructor { } <>
%printer { fprintf(yyo, "%d", $$); } <ival> NUM 'x'
%printer { } <*> <>
%token <ival> NUM 258 "number" STR 259 _("string") ID
%term <ival> OLD_STYLE
%token EOF_TOKEN 0 "end of file"
%token <sval> NAME "identifier" 'c'
%nterm <ival> unit expr
%type <sval> name.opt
%type <ival> expr.list
%left '+' '-'
%right '^' "**"
%nonassoc EQ 300 '='
%binary NE
%precedence PREFIX
%left <ival> LOW
%%
unit: expr.list ;
expr.list: expr | expr.list ',' expr ;
expr: NUM | ID | STR | "number" | 'x' | 'c' | expr '+' expr | expr '-' expr | expr '^' expr | expr "**" expr | expr EQ expr | expr '=' expr | expr NE expr | PREFIX expr | LOW expr | name.opt OLD_STYLE ;
name.opt: %empty | NAME ;
%%
int helper(void) { return 0; }
