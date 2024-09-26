import utils
from records_processing import *

if __name__ == '__main__':
    utils.parse_args()
    utils.config_polars()

    # Load database
    df = utils.read_mysql_db(TABLE_RECORDS)
    # utils.write_csv_db(df)
    # df = utils.read_csv_db()

    # Calculate score for each record
    df = initial_records_cleanup(df)
    df = remove_banned_maps(df)
    df = add_record_ranks(df)
    df = add_top_times(df)
    df = calculate_map_scores(df)

    # Calculate final rating for each player in each category
    df = calculate_player_weighted_scores(df)
    players_rating_df = calculate_player_rating(df)

    # ------------------------------------------------------
    # utils.manual_debug(df, players_rating_df)
    # ------------------------------------------------------

    # utils.write_mysql_db(df, TABLE_RECORDS_SCORES)
    utils.write_mysql_db(players_rating_df, TABLE_PLAYERS_RATINGS)

# TODO:
# - Use lazy evaluation
# - Unify all comments, letter's case, format etc
# - add banned maps
# - update requirements file

# NOTES:
# DB read time: 1.881 seconds
# calculations time: 1.21 seconds
# DB write time: 35.007 seconds

