DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_human` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Human readable time when a record was opened',
  `created` varchar(12) NOT NULL COMMENT 'Epoch time when a record was created',
  `lifetime` varchar(12) NOT NULL COMMENT 'How long does it valid',
  `token` varchar(40) NOT NULL COMMENT 'CSRF token',
  `link` varchar(32) NOT NULL COMMENT 'Link for external access',
  `message` text NOT NULL COMMENT 'Encrypted message',
  `file` longtext DEFAULT NULL COMMENT 'Base64 encoded file attachment',
  `file_name` varchar(100) DEFAULT NULL COMMENT 'Original file name',
  `psk` varchar(1) NOT NULL DEFAULT '0' COMMENT 'Is encrypted with additional password?',
  `views_remaining` int unsigned NOT NULL DEFAULT 1 COMMENT 'Successful text views remaining',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `msglogs`;
CREATE TABLE `msglogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `msgid` varchar(6) NOT NULL COMMENT 'ID of the message from Messages table',
  `msglink` varchar(150) NOT NULL COMMENT 'link for the message',
  `opened` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Time when a record was opened',
  `ip` varchar(20) NOT NULL COMMENT 'IP of an external access',
  `type` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
