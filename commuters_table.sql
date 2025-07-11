-- Updated Commuters Table with Route Coordinates
CREATE TABLE commuters (
    uid VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    home_address_street VARCHAR(500) NOT NULL,
    home_lat DECIMAL(10,8) DEFAULT NULL,
    home_lng DECIMAL(11,8) DEFAULT NULL,
    home_province VARCHAR(100) NOT NULL,
    home_city VARCHAR(100) NOT NULL,
    work_address_street VARCHAR(500) NOT NULL,
    work_lat DECIMAL(10,8) DEFAULT NULL,
    work_lng DECIMAL(11,8) DEFAULT NULL,
    work_province VARCHAR(100) NOT NULL,
    work_city VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL,
    created DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    subscription VARCHAR(50) DEFAULT NULL,
    route_coordinates JSON DEFAULT NULL,
    PRIMARY KEY(uid)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Indexes for better performance
CREATE UNIQUE INDEX UNIQ_COMMUTER_PHONE ON commuters (phone_number);
CREATE INDEX IDX_COMMUTER_TYPE ON commuters (type);
CREATE INDEX IDX_COMMUTER_HOME_CITY ON commuters (home_city);
CREATE INDEX IDX_COMMUTER_WORK_CITY ON commuters (work_city);
CREATE INDEX IDX_COMMUTER_HOME_PROVINCE ON commuters (home_province);
CREATE INDEX IDX_COMMUTER_WORK_PROVINCE ON commuters (work_province);
CREATE INDEX IDX_COMMUTER_SUBSCRIPTION ON commuters (subscription);
CREATE INDEX IDX_COMMUTER_CREATED ON commuters (created);

-- For geospatial queries (if using MySQL 8.0+)
-- CREATE SPATIAL INDEX IDX_COMMUTER_HOME_COORDS ON commuters (home_lat, home_lng);
-- CREATE SPATIAL INDEX IDX_COMMUTER_WORK_COORDS ON commuters (work_lat, work_lng); 