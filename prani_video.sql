-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Počítač: localhost
-- Vytvořeno: Úte 30. zář 2025, 09:23
-- Verze serveru: 5.7.34
-- Verze PHP: 8.3.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `janbrunclik`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `prani_video`
--

CREATE TABLE `prani_video` (
  `id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `jmeno` varchar(80) NOT NULL,
  `ucet` char(9) NOT NULL,
  `castka` decimal(10,2) DEFAULT NULL,
  `stav` enum('prijato','zaplaceno','zaslano','odmitnuto') NOT NULL DEFAULT 'prijato',
  `prani` text,
  `link` varchar(255) DEFAULT NULL,
  `nick` varchar(80) DEFAULT NULL,
  `faktura` varchar(80) DEFAULT NULL,
  `znacka` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `prani_video`
--
ALTER TABLE `prani_video`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `prani_video`
--
ALTER TABLE `prani_video`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
