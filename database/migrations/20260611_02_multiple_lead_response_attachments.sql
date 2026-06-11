-- Allow one lead response to store several uploaded attachment paths as JSON.
ALTER TABLE lead_responses
  MODIFY COLUMN attachment_path TEXT NULL;
