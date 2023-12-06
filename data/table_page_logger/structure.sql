CREATE TABLE IF NOT EXISTS `{TABLE_PREFIX}_table_page_logger`
(
    `{TABLE_PREFIX}_table_page_logger_id` bigint COMMENT 'Unique identifier log element',
    `entity_id`                           int COMMENT 'Related id of the element',
    `event_type`                          int COMMENT 'Type of the event',
    `event_result`                        int COMMENT 'Result',
    `event_result_reference_id`           int       NULL DEFAULT NULL COMMENT 'Result reference id to the table of results with descriptions',
    `created`                             timestamp NULL DEFAULT NULL COMMENT 'Entry date creation',
    `updated`                             timestamp NULL DEFAULT NULL COMMENT 'Entry date update'
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;

ALTER TABLE `{TABLE_PREFIX}_table_page_logger`
    ADD PRIMARY KEY (`{TABLE_PREFIX}_table_page_logger_id`),
    ADD KEY `entity_id` (`entity_id`),
    ADD KEY `event_type` (`event_type`),
    ADD KEY `event_result_reference_id` (`event_result_reference_id`),
    ADD KEY `event_result` (`event_result`);

ALTER TABLE `{TABLE_PREFIX}_table_page_logger`
    MODIFY `{TABLE_PREFIX}_table_page_logger_id` bigint NOT NULL AUTO_INCREMENT;