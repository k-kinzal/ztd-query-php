"""Replay named MySQL binding observations, native reference SQL and mutations."""

import run_server_mysql_named_parameter
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_named_parameter)
