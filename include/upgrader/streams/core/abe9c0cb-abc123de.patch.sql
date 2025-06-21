/**
 * @version v1.18-dev
 * @signature abc123de4567890f1234567890abcdef
 *
 * Add can_create_agents permission to API keys
 * 
 * This migration adds the can_create_agents field to the api_key table
 * to support granular permissions for the Staff API endpoints.
 */

-- Add agent management permission to API keys
ALTER TABLE `%TABLE_PREFIX%api_key`
    ADD `can_create_agents` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `can_exec_cron`;

-- Update schema signature
UPDATE `%TABLE_PREFIX%config` 
    SET `value` = 'abc123de4567890f1234567890abcdef'
    WHERE `key` = 'schema_signature' AND `namespace` = 'core';
