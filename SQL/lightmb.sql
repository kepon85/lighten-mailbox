-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le :  sam. 31 oct. 2020 à 14:18
-- Version du serveur :  10.1.47-MariaDB-0+deb9u1
-- Version de PHP :  7.3.20-1+0~20200710.65+debian9~1.gbpc9cbeb

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `lightmb`
--

-- --------------------------------------------------------

--
-- Structure de la table `archive`
--

CREATE TABLE `archive` (
  `session_id` int(11) NOT NULL,
  `file` varchar(255) NOT NULL,
  `dateCreate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `open`
--

CREATE TABLE `open` (
  `id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `domain` varchar(200) NOT NULL,
  `mx` varchar(250) NOT NULL,
  `dateCreate` int(11) NOT NULL,
  `imap_server` varchar(200) NOT NULL,
  `imap_port` int(11) NOT NULL,
  `imap_user` varchar(2) NOT NULL,
  `imap_secure` tinyint(1) NOT NULL,
  `imap_auth` tinyint(1) NOT NULL,
  `imap_cert` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `session`
--

CREATE TABLE `session` (
  `id` int(11) NOT NULL,
  `user` varchar(250) NOT NULL,
  `domain` varchar(250) NOT NULL,
  `dateCreate` int(11) NOT NULL,
  `imap_folder` text,
  `dateStart` int(11) DEFAULT NULL,
  `dateEnd` int(11) DEFAULT NULL,
  `what` tinyint(1) DEFAULT NULL COMMENT '1: archive + délete / 2 : archive / 3 delte',
  `format` varchar(4) DEFAULT NULL,
  `total_size` int(11) DEFAULT NULL,
  `total_nb` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `spooler`
--

CREATE TABLE `spooler` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(200) DEFAULT NULL,
  `task` int(1) NOT NULL COMMENT '1 : archive / 2 : sup',
  `status` tinyint(1) NOT NULL COMMENT '0 : error / 1 : Attente apro / 2 attente exec / 3 / en cours / 5 terminé'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `archive`
--
ALTER TABLE `archive`
  ADD PRIMARY KEY (`session_id`);

--
-- Index pour la table `open`
--
ALTER TABLE `open`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `session`
--
ALTER TABLE `session`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `spooler`
--
ALTER TABLE `spooler`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `open`
--
ALTER TABLE `open`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `spooler`
--
ALTER TABLE `spooler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
