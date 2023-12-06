CREATE TABLE IF NOT EXISTS `event_result_reference`
(
    `event_result_reference_id` bigint COMMENT 'Unique identifier log element',
    `event_result`              text COMMENT 'description of the event',
    `event_result_hash`         varchar(32) COMMENT 'md5 hash of the description',
    `created`                   timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
    `updated`                   timestamp NULL DEFAULT NULL COMMENT 'Entry date update'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8;

ALTER TABLE `event_result_reference`
    ADD PRIMARY KEY (`event_result_reference_id`),
    ADD KEY `idx-event-result-hash` (`event_result_hash`);

ALTER TABLE `event_result_reference`
    MODIFY `event_result_reference_id` bigint NOT NULL AUTO_INCREMENT;