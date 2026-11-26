"""Replay mode selection, all projection observations and native failure witnesses."""

import run_server_mysql_ansi_projection
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_ansi_projection)
