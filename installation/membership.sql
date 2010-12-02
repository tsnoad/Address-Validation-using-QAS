-- Table to hold the verified address for the membership database
CREATE TABLE contact_verified (
  sys_contact_verified_id BIGSERIAL PRIMARY KEY,
  sys_contact_id BIGINT,
  address_end_date TIMESTAMP,
  contact TEXT,
  suburb TEXT,
  state TEXT,
  postcode TEXT,
  country TEXT,
  return_code TEXT,
  verifiable BOOLEAN DEFAULT FALSE,
  contact_verified TEXT,
  suburb_verified TEXT,
  state_verified TEXT,
  postcode_verified TEXT,
  country_verified TEXT,
  FOREIGN KEY (sys_contact_id) REFERENCES contact(sys_contact_id) MATCH FULL ON UPDATE CASCADE ON DELETE CASCADE
);