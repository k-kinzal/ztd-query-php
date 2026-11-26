"""Replay MySQL comma product observations across all registered releases."""

import run_server_mysql_from_list
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_from_list)
