CREATE TABLE {$NAMESPACE}_auth.auth_factorprovider (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phid VARBINARY(64) NOT NULL,
  providerFactorKey VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT},
  status VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT},
  properties LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT},
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
