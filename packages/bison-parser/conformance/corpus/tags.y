%language "C++"
%skeleton "lalr1.cc"
%define api.value.type variant
%token <std::vector<std::pair<int, int>>> PAIRS
%token <std::map<std::string,std::vector<int>>> MAPS
%token <std::string> STRING
%nterm <std::vector<int>> list
%type <int> number
%%
list: %empty | list number ;
number: STRING { $$ = 1; } | PAIRS | MAPS ;
