"""Replay registered MySQL table definition copy observations."""

import run_server_mysql_create_like
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_like)
