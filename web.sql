--
-- Table structure for table `_web_cache`
--
CREATE TABLE `_web_cache` (
  `id` int(11) NOT NULL,
  `url_hash` char(32) NOT NULL,
  `url` varchar(250) NOT NULL,
  `title` varchar(100) NOT NULL,
  `visits` int(11) NOT NULL DEFAULT '1',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `_web_history`
--
CREATE TABLE `_web_history` (
  `id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `title` varchar(250) NOT NULL,
  `url` varchar(250) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `_web_user_settings`
--
CREATE TABLE `_web_user_settings` (
  `id_person` int(11) NOT NULL,
  `save_mode` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for table `_web_user_settings`
--
ALTER TABLE `_web_user_settings`
  ADD PRIMARY KEY (`id_person`);
COMMIT;

--
-- Indexes for table `_web_history`
--
ALTER TABLE `_web_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `person_id` (`person_id`);

--
-- AUTO_INCREMENT for table `_web_history`
--
ALTER TABLE `_web_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

--
-- Indexes for table `_web_cache`
--
ALTER TABLE `_web_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url_hash` (`url_hash`);

--
-- AUTO_INCREMENT for table `_web_cache`
--
ALTER TABLE `_web_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;