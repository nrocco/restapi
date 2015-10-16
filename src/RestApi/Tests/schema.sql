CREATE TABLE "todos" (
    "id"          INTEGER       PRIMARY KEY ASC,
    "created"     DATETIME      DEFAULT current_timestamp,
    "updated"     DATETIME      DEFAULT current_timestamp,
    "user_id"     VARCHAR(200)  NOT NULL DEFAULT '',
    "category"    VARCHAR(200)  NOT NULL DEFAULT 'inbox',
    "description" TEXT          NOT NULL DEFAULT '',
    "done"        INTEGER       NOT NULL DEFAULT 0 CHECK(done IN (0, 1)),
    "urgency"     INTEGER       NOT NULL DEFAULT 2 CHECK(urgency IN (1, 2, 3))
);

CREATE VIEW "categories" AS SELECT DISTINCT category AS name FROM "todos";
