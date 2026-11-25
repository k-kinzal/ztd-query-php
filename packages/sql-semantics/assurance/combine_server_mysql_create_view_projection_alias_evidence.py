"""Independently replay native persistent view alias evidence."""

from combine_server_join_evidence import main
import run_server_mysql_create_view_projection_alias


if __name__ == "__main__":
    main(run_server_mysql_create_view_projection_alias)
