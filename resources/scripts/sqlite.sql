-- #!sqlite
-- #{ sw
-- #{ table.players
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
-- #{ table.lobby
CREATE TABLE IF NOT EXISTS lobby
(
    lobbyX    INTEGER DEFAULT 0,
    lobbyY    INTEGER DEFAULT 0,
    lobbyZ    INTEGER DEFAULT 0,
    worldName VARCHAR(256) NOT NULL -- https://en.wikipedia.org/wiki/Long_filename
);
-- # }
-- # { create.player
-- #   :playerName string
INSERT OR IGNORE INTO players(playerName)
VALUES (:playerName);
-- #}
-- #{ select.player
-- #  :playerName string
SELECT *
FROM players
WHERE playerName = :playerName;
-- #}
-- #{ update.player
-- #  :playerName string
-- #  :playerTime int
-- #  :kills int
-- #  :deaths int
-- #  :wins int
-- #  :lost int
-- #  :dataOffset string
UPDATE players
SET playerName = :playerName,
    playerTime = :playerTime,
    kills      = :kills,
    deaths     = :deaths,
    wins       = :wins,
    lost       = :lost,
    data       = :dataOffset
WHERE playerName = :playerName;
-- #}
-- #{ select.lobby
SELECT *
FROM lobby
WHERE worldName IS NOT NULL;
-- #}
-- #{ create.lobby
-- #  :lobbyX int
-- #  :lobbyY int
-- #  :lobbyZ int
-- #  :worldName string
INSERT INTO lobby(lobbyX, lobbyY, lobbyZ, worldName)
VALUES (:lobbyX, :lobbyY, :lobbyZ, :worldName);
-- #}
-- #{ update.lobby
-- #  :lobbyX int
-- #  :lobbyY int
-- #  :lobbyZ int
-- #  :worldName string
-- #  :worldNameData string
UPDATE lobby
SET lobbyX    = :lobbyX,
    lobbyY    = :lobbyY,
    lobbyZ    = :lobbyZ,
    worldName = :worldName
WHERE worldName = :worldNameData;
-- #}
-- #{ select.players
SELECT *
FROM players
ORDER BY kills DESC LIMIT 5;
-- #}
-- #}
