%{
#include <stdio.h>
/* a } in a prologue comment */
static const char *s = "%}";
%}
%token X
%%
/* rules with an %{ %} lookalike inside code */
start: X { printf("%s", "{ } \" ' /* */ // }"); } ;
extra: X
     | start ;
%%
/* epilogue with %% inside a string: "%%" */
int main(void) { return 0; }
