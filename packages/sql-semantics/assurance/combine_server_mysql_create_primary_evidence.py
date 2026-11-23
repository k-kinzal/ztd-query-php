"""Replay the native MySQL signed primary key ledger."""

import run_server_mysql_create_primary
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_primary)
