-- #!sqlite

-- #{ table
    -- #{ players
CREATE TABLE IF NOT EXISTS players
(
    playerName VARCHAR(32) PRIMARY KEY NOT NULL,
    playerTime INTEGER DEFAULT 0,
    kills      INTEGER DEFAULT 0,
    deaths     INTEGER DEFAULT 0,
    wins       INTEGER DEFAULT 0,
    lost       INTEGER DEFAULT 0,
    data       TEXT    DEFAULT NULL
);
    -- #}
    -- #{ lobby
CREATE TABLE IF NOT EXISTS lobby
(
    lobbyX    INTEGER DEFAULT 0,
    lobbyY    INTEGER DEFAULT 0,
    lobbyZ    INTEGER DEFAULT 0,
    worldName VARCHAR(256) PRIMARY KEY NOT NULL -- https://en.wikipedia.org/wiki/Long_filename
);
    -- #}
-- #}

-- #{ data
    -- #{ createData
    -- #   :playerName string
INSERT OR IGNORE INTO players(playerName)
VALUES (:playerName);
    -- #}
    -- #{ setLobby
    -- #  :lobbyX int
    -- #  :lobbyY int
    -- #  :lobbyZ int
    -- #  :worldName string
INSERT OR
REPLACE INTO lobby(lobbyX, lobbyY, lobbyZ, worldName)
VALUES (:lobbyX, :lobbyY, :lobbyZ, :worldName);
    -- #}
    -- #{ selectLobby
SELECT *
FROM lobby
WHERE worldName IS NOT NULL;
    -- #}
    -- #{ selectData
    -- #  :playerName string
SELECT *
FROM players
WHERE playerName = :playerName;
    -- #}
    -- #{ changeOffset
    -- #  :dataOffset string
    -- #  :playerName string
UPDATE players
SET data = :dataOffset
WHERE playerName = :playerName;
    -- #}
    -- #{ selectEntries
SELECT *
FROM players
ORDER BY wins DESC LIMIT 5;
    -- #}
    -- #{ addKills
    -- #  :playerName string
UPDATE players
SET kills = kills + 1
WHERE playerName = :playerName;
    -- #}
    -- #{ addDeaths
    -- #  :playerName string
UPDATE players
SET deaths = deaths + 1,
    lost   = lost + 1
WHERE playerName = :playerName;
    -- #}
    -- #{ addWins
    -- #  :playerName string
UPDATE players
SET wins = wins + 1
WHERE playerName = :playerName;
    -- #}
    -- #{ addTimer
    -- #  :playerName string
    -- #  :playerTime int
UPDATE players
SET playerTime = playerTime + :playerTime
WHERE playerName = :playerName;
    -- #}
-- #}