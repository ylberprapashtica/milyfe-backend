-- Update the captures_status_check constraint to include 'implemented'
-- Run this SQL command in your PostgreSQL database

-- Drop the existing constraint
ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_status_check;

-- Add the updated constraint with 'implemented' status
ALTER TABLE captures ADD CONSTRAINT captures_status_check 
    CHECK (status IN ('fleeting', 'reviewed', 'organized', 'implemented', 'forgotten', 'deleted'));
