"""Independently replay MySQL group predicate observations."""

import run_server_mysql_grouped_having
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_grouped_having)
