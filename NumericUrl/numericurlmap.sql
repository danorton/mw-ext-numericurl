--
-- The NumericUrlMap table describes the mappings from keys to URLs.
--
DROP TABLE IF EXISTS /*_*/numericurlmap;
CREATE TABLE IF NOT EXISTS /*_*/numericurlmap (
  -- Primary key.
  numap_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
 
  -- Mapping key.
  numap_key varbinary(32) NOT NULL,
  
  -- URL
  numap_link blob NOT NULL,
  
  -- Enabled (else it's disabled/inactive/expired/reserved/paused/blocked/suspended/...).
  numap_enabled bool NOT NULL DEFAULT 0,
  
  -- Flag that indicates if the scheme is insecure.
  numap_insecure bool NOT NULL DEFAULT 1,
  
  -- Creator/owner.
  numap_creator int unsigned NOT NULL DEFAULT 0,
  
  -- Number of groups that have follow permission (see table numericurlgroups).
  -- If this is non-zero, only members of these groups can follow this URL.
  numap_groups_count int unsigned NOT NULL DEFAULT 0,
  
  -- Time before which the link is not active.
  numap_embargo varbinary(14) DEFAULT NULL,
  
  -- Time at or after which the link is not active.
  numap_expiry varbinary(14) DEFAULT NULL,
 
  -- Creation timestamp.
  numap_timestamp varbinary(14) NOT NULL DEFAULT ''
  
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/numap_key ON /*_*/numericurlmap (numap_key);
CREATE INDEX /*i*/numap_link ON /*_*/numericurlmap (numap_link(60));
