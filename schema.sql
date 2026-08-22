DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'Account that created the message',
  `created_human` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Human readable time when a record was opened',
  `created` varchar(12) NOT NULL COMMENT 'Epoch time when a record was created',
  `lifetime` varchar(12) NOT NULL COMMENT 'How long does it valid',
  `token` varchar(40) NOT NULL COMMENT 'CSRF token',
  `link` varchar(32) NOT NULL COMMENT 'Link for external access',
  `short_link` varchar(12) DEFAULT NULL COMMENT 'Optional built-in short URL code',
  `message` text NOT NULL COMMENT 'Encrypted message',
  `file` longtext DEFAULT NULL COMMENT 'Base64 encoded file attachment',
  `file_name` varchar(100) DEFAULT NULL COMMENT 'Original file name',
  `psk` varchar(1) NOT NULL DEFAULT '0' COMMENT 'Is encrypted with additional password?',
  `views_remaining` int unsigned NOT NULL DEFAULT 1 COMMENT 'Successful text views remaining',
  PRIMARY KEY (`id`),
  UNIQUE KEY `messages_short_link` (`short_link`),
  KEY `messages_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `message_history`;
CREATE TABLE `message_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `viewed` tinyint(1) NOT NULL DEFAULT 0,
  `viewed_at` DATETIME DEFAULT NULL,
  `sender_copy` longtext DEFAULT NULL COMMENT 'Encrypted text retained for the authenticated creator',
  `sender_token` varchar(40) DEFAULT NULL COMMENT 'Token used to derive the sender-copy IV',
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_history_message_id` (`message_id`),
  KEY `message_history_sent_at` (`sent_at`),
  KEY `message_history_user_id` (`user_id`)
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
