
-- ---------------------------------------------------------------------
-- 1. TRIGGER + FUNCTION: audit every stock change in inventory
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_audit (
    audit_id     SERIAL PRIMARY KEY,
    inventory_id INTEGER      NOT NULL,
    branch_id    INTEGER      NOT NULL REFERENCES branches(branch_id),
    item_name    VARCHAR(100) NOT NULL,
    old_qty      INTEGER,
    new_qty      INTEGER      NOT NULL,
    changed_at   TIMESTAMP    NOT NULL DEFAULT now(),
    changed_by   TEXT         NOT NULL DEFAULT session_user
);

-- SECURITY DEFINER lets staff trigger the audit insert without direct rights on the audit table
CREATE OR REPLACE FUNCTION fn_audit_inventory()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO inventory_audit (inventory_id, branch_id, item_name, old_qty, new_qty)
        VALUES (NEW.inventory_id, NEW.branch_id, NEW.item_name, NULL, NEW.stock_qty);
    ELSIF NEW.stock_qty IS DISTINCT FROM OLD.stock_qty THEN
        INSERT INTO inventory_audit (inventory_id, branch_id, item_name, old_qty, new_qty)
        VALUES (NEW.inventory_id, NEW.branch_id, NEW.item_name, OLD.stock_qty, NEW.stock_qty);
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_inventory_audit ON inventory;
CREATE TRIGGER trg_inventory_audit
    AFTER INSERT OR UPDATE ON inventory
    FOR EACH ROW EXECUTE FUNCTION fn_audit_inventory();

-- ---------------------------------------------------------------------
-- 2. FUNCTION: items at or below their reorder level (decision support)
--    Usage: SELECT * FROM fn_low_stock(1);
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_low_stock(p_branch INTEGER)
RETURNS TABLE (item_name VARCHAR, stock_qty INTEGER, reorder_level INTEGER)
LANGUAGE sql
STABLE
AS $$
    SELECT i.item_name, i.stock_qty, i.reorder_level
    FROM inventory i
    WHERE i.branch_id = p_branch
      AND i.stock_qty <= i.reorder_level
    ORDER BY i.stock_qty;
$$;

-- ---------------------------------------------------------------------
-- 3. ROLES AND PRIVILEGES: two roles with different privileges
-- ---------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'litson_owner_role') THEN
        CREATE ROLE litson_owner_role NOLOGIN;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'litson_staff_role') THEN
        CREATE ROLE litson_staff_role NOLOGIN;
    END IF;
END
$$;

GRANT USAGE ON SCHEMA public TO litson_owner_role, litson_staff_role;

-- Owner: read, add, change and delete everything
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO litson_owner_role;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO litson_owner_role;

-- Staff: read everything, but only add/update inventory, production and logs. No DELETE.
GRANT SELECT ON ALL TABLES IN SCHEMA public TO litson_staff_role;
GRANT INSERT, UPDATE ON inventory, production, inventory_log TO litson_staff_role;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO litson_staff_role;

-- Optional: attach a role to the login user the app uses.
-- Only restricts litson_staff if it is NOT the owner of the tables.
-- GRANT litson_staff_role TO litson_staff;

-- Check who owns the tables:
SELECT tablename, tableowner FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;

-- PROOF (run these by hand and screenshot the results):
--   SET ROLE litson_staff_role;
--   SELECT count(*) FROM inventory;          -- works
--   DELETE FROM inventory WHERE false;       -- ERROR: permission denied for table inventory
--   RESET ROLE;
--   SET ROLE litson_owner_role;
--   DELETE FROM inventory WHERE false;       -- works (0 rows)
--   RESET ROLE;

-- ---------------------------------------------------------------------
-- 4. EXPLAIN: show that the indexes are used
-- ---------------------------------------------------------------------
-- The tables are small, so PostgreSQL normally picks a Seq Scan.
-- Turning seqscan off for this session shows that the index is usable.
SET enable_seqscan = off;

-- Uses idx_production_branch_date
EXPLAIN ANALYZE
SELECT * FROM production
WHERE branch_id = 1 AND production_date >= CURRENT_DATE - 30;

-- Uses the unique index inventory_branch_id_item_name_key (the lookup in useInventory)
EXPLAIN ANALYZE
SELECT * FROM inventory
WHERE branch_id = 1 AND item_name = 'Pouch';

RESET enable_seqscan;
