--
-- The numericurlregions table lists the user regions associated with a numeric URL.
--
DROP TABLE IF EXISTS /*_*/numericurlregions;
CREATE TABLE IF NOT EXISTS /*_*/numericurlregions (
  -- Numeric URL map instance ID.
  nurgn_numap_id int unsigned NOT NULL,
  
  -- Region name
  nurgn_region varbinary(255) NOT NULL default ''
  
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/nurgn_mapid_region ON /*_*/numericurlregions (nurgn_numap_id, nurgn_region);
CREATE INDEX /*i*/nurgn_numap_id ON /*_*/numericurlregions (nurgn_numap_id);
