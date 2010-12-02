-- Table to hold the verified address for the authentication database
CREATE TABLE address_verified (
  sys_address_verified_id BIGSERIAL PRIMARY KEY,
  sys_address_id BIGINT,
  address_end_date TIMESTAMP,
  address TEXT,
  suburb TEXT,
  state TEXT,
  postcode TEXT,
  country TEXT,
  return_code TEXT,
  verifiable BOOLEAN DEFAULT FALSE,
  address_verified TEXT,
  suburb_verified TEXT,
  state_verified TEXT,
  postcode_verified TEXT,
  country_verified TEXT,
  FOREIGN KEY (sys_address_id) REFERENCES address(sys_address_id) MATCH FULL ON UPDATE CASCADE ON DELETE CASCADE
);