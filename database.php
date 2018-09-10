<?php

// The SQL to uninstall this tool
$DATABASE_UNINSTALL = array(
);

// The SQL to create the tables if they don't exist
$DATABASE_INSTALL = array(
    array( "{$CFG->dbprefix}emoji_rating",
        "create table {$CFG->dbprefix}emoji_rating (
    emoji_id      INTEGER NOT NULL AUTO_INCREMENT,
    context_id    INTEGER NOT NULL,
    link_id       INTEGER NOT NULL,
    user_id       INTEGER NOT NULL,
    rating_type   TINYINT NOT NULL,
    prompt        TEXT NOT NULL,
    modified      datetime NOT NULL,
    
    PRIMARY KEY(emoji_id)
) ENGINE = InnoDB DEFAULT CHARSET=utf8"),
    array( "{$CFG->dbprefix}emoji_response",
        "create table {$CFG->dbprefix}emoji_response (
    response_id     INTEGER NOT NULL AUTO_INCREMENT,
    emoji_id        INTEGER NOT NULL,
    user_id         INTEGER NOT NULL,
    response        TINYINT NOT NULL,
    modified        datetime NOT NULL,

    CONSTRAINT `{$CFG->dbprefix}er_ibfk_1`
        FOREIGN KEY (`emoji_id`)
        REFERENCES `{$CFG->dbprefix}emoji_rating` (`emoji_id`)
        ON DELETE CASCADE,

    PRIMARY KEY(response_id)
) ENGINE = InnoDB DEFAULT CHARSET=utf8")
);
