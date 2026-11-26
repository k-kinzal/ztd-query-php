"""Replay the independent native MySQL WITH mode and scope observations."""

import run_server_mysql_ansi_with
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_ansi_with)
