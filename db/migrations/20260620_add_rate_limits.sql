CREATE TABLE IF NOT EXISTS rate_limits (
  id int(11) NOT NULL AUTO_INCREMENT,
  ip_address varchar(45) NOT NULL,
  action varchar(50) NOT NULL,
  attempts int(11) NOT NULL DEFAULT '1',
  locked_until datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY ip_action_idx (ip_address,action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
