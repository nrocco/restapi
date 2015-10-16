DROP TABLE IF EXISTS "todos";
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

DROP TRIGGER IF EXISTS "on_after_update_todos_set_updated";
CREATE TRIGGER "on_after_update_todos_set_updated"
    AFTER UPDATE ON "todos" FOR EACH ROW
    BEGIN
        UPDATE todos
        SET updated = current_timestamp
        WHERE id = OLD.id;
    END;

DROP TRIGGER IF EXISTS "on_before_update_todos_read_only_fields";
CREATE TRIGGER "on_before_update_todos_read_only_fields"
    BEFORE UPDATE OF "created", "user_id", "id" ON "todos"
    BEGIN
        SELECT RAISE(ABORT, "Cannot change read-only fields: created, user_id, id");
    END;

DROP TRIGGER IF EXISTS "on_before_update_todos_check_if_done";
CREATE TRIGGER "on_before_update_todos_check_if_done"
    BEFORE UPDATE OF "description", "urgency" ON "todos" WHEN OLD.done = 1
    BEGIN
        SELECT RAISE(ABORT, "Cannot change a todo when it is marked as done.");
    END;

CREATE INDEX todos_user_id ON "todos" ("user_id");
CREATE INDEX todos_done ON "todos" ("done");
CREATE INDEX todos_urgency ON "todos" ("urgency");

DROP VIEW IF EXISTS "categories";
CREATE VIEW "categories" AS
    SELECT DISTINCT category AS name FROM "todos";
