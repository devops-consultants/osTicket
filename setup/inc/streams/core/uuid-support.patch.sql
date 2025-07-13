-- Database migration to support longer username fields for external IDP integration
-- This change increases the username field length to support UUIDs from IDPs like Keycloak

ALTER TABLE `%TABLE_PREFIX%staff` 
MODIFY COLUMN `username` VARCHAR(64) NOT NULL DEFAULT '';

-- Update any indexes that reference the username field if necessary
-- Note: Check if there are any unique indexes on username that might need adjustment
