import polars as pl
import banned_maps as bm
from globals import *

# CS = Column Schema
# columns for "records_score" table
CS_RECORDS_SCORES = [
        CN_PLAYER_NAME,
        CN_MDD_PLAYER_ID,
        CN_DR_PLAYER_ID,
        CN_MAP,
        CN_PHYSICS,
        CN_MODE,
        CN_TIME_MS,
        ]

# Below must be in syncs with:
# app/Models/PlayersRatings.php
CS_PLAYERS_RATINGS = [
        CN_PLAYER_NAME,
        CN_MDD_PLAYER_ID,
        CN_DR_PLAYER_ID,
        CN_PHYSICS,
        CN_MODE,
        CN_PLAYER_RATING
        ]

MIN_TOTAL_PARTICIPATORS = 5

def add_record_ranks(df, rank_method="min"):
    # Calculate total participators for each map, physics and mode
    total_participators = (df
                           .group_by([CN_MAP, CN_PHYSICS, CN_MODE])
                           .agg(pl.count(CN_MDD_PLAYER_ID)
                                .alias(CN_TOTAL_PARTICIPATORS)))

    # Join the total participators data back to the original DataFrame
    df = df.join(total_participators, on=[CN_MAP, CN_PHYSICS, CN_MODE])

    # Calculate rank for each player on each map, physics and mode
    df = df.sort([CN_MAP, CN_PHYSICS, CN_MODE, CN_TIME_MS])
    df = df.with_columns(pl.col(CN_TIME_MS)
            .rank(method=rank_method)
            # .rank(method="dense") # No rank number will be skipped
            # .rank(method="min") # Rank numbers will be skipped if there are ties, this is q3df approach
            .over([CN_MAP, CN_PHYSICS, CN_MODE])
            .alias(CN_RECORD_RANK))

    return df

def add_top_times(df):
    top1_times = df.filter((pl.col(CN_RECORD_RANK) == 1)).select([CN_MAP, CN_PHYSICS, CN_MODE, CN_TIME_MS]).unique()
    top2_times = df.filter((pl.col(CN_RECORD_RANK) == 2)).select([CN_MAP, CN_PHYSICS, CN_MODE, CN_TIME_MS]).unique()

    top1_times = top1_times.rename({CN_TIME_MS: CN_TOP1_TIME})
    top2_times = top2_times.rename({CN_TIME_MS: CN_TOP2_TIME})

    # Join the original DataFrame with the 'top_times' DataFrame on 'map' and 'physics'
    df = df.join(top1_times, on=[CN_MAP, CN_PHYSICS, CN_MODE], how="left").fill_null(strategy="zero")
    df = df.join(top2_times, on=[CN_MAP, CN_PHYSICS, CN_MODE], how="left").fill_null(strategy="zero")

    return df

# This function calculates the map score for each record
# Main score calculation algorithm
def calculate_map_scores(df):
    score = (
            1000 *
            pl.when(pl.col(CN_TOP1_TIME) != pl.col(CN_TIME_MS))
            .then(pl.col(CN_TOP1_TIME))
            .otherwise(
                pl.when((pl.col(CN_TOTAL_PARTICIPATORS)) > 1)
                .then(
                    pl.when(pl.col(CN_TOP1_TIME) * 2 > pl.col(CN_TOP2_TIME))
                    .then(
                        # top2 will be zero if there is no rank 2
                        # at this point it means all participators achieved the same time
                        pl.when(pl.col(CN_TOP2_TIME) != 0)
                        .then(pl.col(CN_TOP2_TIME))
                        .otherwise(pl.col(CN_TOP1_TIME))
                        )
                    .otherwise(pl.col(CN_TOP1_TIME) * 2)
                    )
                .otherwise(pl.col(CN_TOP1_TIME))
                )
            /
            pl.col(CN_TIME_MS) *
            (0.988 ** (pl.col(CN_RECORD_RANK) - 1))
            )

    # Add the 'map_score' column to the DataFrame
    df = df.with_columns(score
                         .round(3)
                         .clip(upper_bound=1100)
                         .alias(CN_MAP_SCORE))

    return df

def calculate_player_weighted_scores(df):
    df = df.sort([CN_MDD_PLAYER_ID, CN_PHYSICS, CN_MODE, CN_MAP_SCORE],
                 descending=[False, False, False, True])

    # Assign rank to each score within each 'player_id' + 'physics' + 'mode' group
    df = df.with_columns(pl.col(CN_MAP_SCORE)
                         .rank(method="ordinal", descending=True)
                         .over([CN_MDD_PLAYER_ID, CN_PHYSICS, CN_MODE])
                         .alias(CN_PLAYER_SCORE_RANK))

    # Calculate weighted score
    df = df.with_columns((pl.col(CN_MAP_SCORE) * (0.98 ** (pl.col(CN_PLAYER_SCORE_RANK) - 1)))
                         .round(3)
                         .alias(CN_PLAYER_SCORE_WEIGHTED))

    return df

def calculate_player_rating(df):
    # Sum all weighted scores for each 'player_id' + 'physics' + 'mode' group
    players_rating_df = (df
                         .filter(pl.col(CN_TOTAL_PARTICIPATORS) >= MIN_TOTAL_PARTICIPATORS)
                         .group_by([CN_MDD_PLAYER_ID, CN_PHYSICS, CN_MODE])
                         .agg(pl.col(CN_PLAYER_SCORE_WEIGHTED)
                              .sum()
                              .alias(CN_PLAYER_RATING),
                              pl.col(CN_PLAYER_NAME).first(),
                              pl.col(CN_DR_PLAYER_ID).first()
                              )
                         .select(CS_PLAYERS_RATINGS)
                         .sort([CN_PHYSICS, CN_MODE, CN_PLAYER_RATING], descending=[False, False, True])
                         )

    return players_rating_df

def initial_records_cleanup(df):
    # Remove deleted records
    df = df.filter(df[CN_DELETED_AT].is_null())

    # Select only necessary columns
    df = df.select(CS_RECORDS_SCORES)

    # Keep only "run" records
    # df = df.filter(df[CN_MODE] == MODE_RUN)

    return df

def remove_banned_maps(df):
    # In future banned maps should be taken from "maps_tags" DB table
    banned_maps_df = pl.DataFrame(bm.get_banned_maps(),
                          orient="row",
                          schema=[(CN_MAP, pl.String),
                                  (CN_PHYSICS, pl.String),
                                  (CN_MODE, pl.String),
                                  (CN_TAG_BANNED, pl.Boolean)])

    df = df.join(banned_maps_df, on=[CN_MAP, CN_PHYSICS, CN_MODE], how="left")

    # Below might be deleted and later just check for "null" instead of "False"
    df = df.with_columns(pl.col(CN_TAG_BANNED).replace(None, False))

    # Filter out rows where 'BANNED' is True
    df = df.filter(pl.col(CN_TAG_BANNED) == False)

    # Drop the 'banned' column
    df = df.drop([CN_TAG_BANNED])

    return df

def get_players(df):
    # Create column with total records sum
    total_records_sum = (df
                         .group_by(CN_MDD_PLAYER_ID)
                         .agg(pl.col(CN_PLAYER_NAME).first(),
                              pl.col(CN_TIME_MS).count().alias("total_records_count")))

    print(total_records_sum.head())

    df = df.join(total_records_sum, on=[CN_MDD_PLAYER_ID], how="left").fill_null(strategy="zero")

    return df
