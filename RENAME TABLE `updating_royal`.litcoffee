--New DB

RENAME TABLE `updating_royal`.`basecolor` TO `updating_royal`.`basecolor_v3`;
RENAME TABLE `updating_royal`.`colorants` TO `updating_royal`.`colorants_v3`;
RENAME TABLE `updating_royal`.`fandecks` TO `updating_royal`.`fandecks_v3`;
RENAME TABLE `updating_royal`.`products` TO `updating_royal`.`products_v3`;
RENAME TABLE `updating_royal`.`shadecolors` TO `updating_royal`.`shadecolors_v3`;

-- fandecks_v3
ALTER TABLE `fandecks_v3` CHANGE `royal_acrylic_washalbe_distemper` `rawd` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_glow_interior_luxury_emulsion` `rgie` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_protect_luxury_exterior_emulsion` `rplee` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_sleek_luxury_emulsion` `rsie` TINYINT(1) NULL DEFAULT '0', CHANGE `ultra_weather_clad_luxury_exterior_emulsion` `uwcee` TINYINT(1) NULL DEFAULT '0', CHANGE `velvet_touch_interior_permium_emulsion` `vtie` TINYINT(1) NULL DEFAULT '0', CHANGE `weather_clad_luxury_exterior_emulsion` `wcee` TINYINT(1) NULL DEFAULT '0', CHANGE `yatra_acrylic_washable_distemper` `yad` TINYINT(1) NULL DEFAULT '0', CHANGE `yatra_interior_emulsion` `yie` TINYINT(1) NULL DEFAULT '0';

-- shadecolors_v3
ALTER TABLE `shadecolors_v3` CHANGE `royal_acrylic_washalbe_distemper` `rawd` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_glow_interior_luxury_emulsion` `rgie` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_protect_luxury_exterior_emulsion` `rplee` TINYINT(1) NULL DEFAULT '0', CHANGE `royal_sleek_luxury_emulsion` `rsie` TINYINT(1) NULL DEFAULT '0', CHANGE `ultra_weather_clad_luxury_exterior_emulsion` `uwcee` TINYINT(1) NULL DEFAULT '0', CHANGE `velvet_touch_interior_permium_emulsion` `vtie` TINYINT(1) NULL DEFAULT '0', CHANGE `weather_clad_luxury_exterior_emulsion` `wcee` TINYINT(1) NULL DEFAULT '0', CHANGE `yatra_acrylic_washable_distemper` `yad` TINYINT(1) NULL DEFAULT '0', CHANGE `yatra_interior_emulsion` `yie` TINYINT(1) NULL DEFAULT '0';

-- royal_acrylic_washalbe_distemper convert into float
ALTER TABLE `royal_acrylic_washalbe_distemper` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- royal_glow_interior_luxury_emulsion convert into float
ALTER TABLE `royal_glow_interior_luxury_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- royal_protect_luxury_exterior_emulsion convert into float
ALTER TABLE `royal_protect_luxury_exterior_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- royal_sleek_luxury_emulsion convert into float
ALTER TABLE `royal_sleek_luxury_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- ultra_weather_clad_luxury_exterior_emulsion convert into float
ALTER TABLE `ultra_weather_clad_luxury_exterior_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- velvet_touch_interior_permium_emulsion convert into float
ALTER TABLE `velvet_touch_interior_permium_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- weather_clad_luxury_exterior_emulsion convert into float
ALTER TABLE `weather_clad_luxury_exterior_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- yatra_acrylic_washable_distemper convert into float
ALTER TABLE `yatra_acrylic_washable_distemper` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- yatra_interior_emulsion convert into float
ALTER TABLE `yatra_interior_emulsion` CHANGE `XT` `XT` FLOAT NULL DEFAULT '0.00', CHANGE `TT` `TT` FLOAT NULL DEFAULT '0.00', CHANGE `LS` `LS` FLOAT NULL DEFAULT '0.00', CHANGE `MS` `MS` FLOAT NULL DEFAULT '0.00', CHANGE `RT` `RT` FLOAT NULL DEFAULT '0.00', CHANGE `FT` `FT` FLOAT NULL DEFAULT '0.00', CHANGE `KS` `KS` FLOAT NULL DEFAULT '0.00', CHANGE `MM` `MM` FLOAT NULL DEFAULT '0.00', CHANGE `RS` `RS` FLOAT NULL DEFAULT '0.00', CHANGE `VT` `VT` FLOAT NULL DEFAULT '0.00', CHANGE `PT` `PT` FLOAT NULL DEFAULT '0.00', CHANGE `ZT` `ZT` FLOAT NULL DEFAULT '0.00', CHANGE `MT` `MT` FLOAT NULL DEFAULT '0.00', CHANGE `LT` `LT` FLOAT NULL DEFAULT '0.00', CHANGE `US` `US` FLOAT NULL DEFAULT '0.00', CHANGE `ST` `ST` FLOAT NULL DEFAULT '0.00';

-- basecolor_v3
ALTER TABLE `basecolor_v3` CHANGE `unitPrice1` `unitPrice1` FLOAT NULL, CHANGE `unitPrice2` `unitPrice2` FLOAT NULL, CHANGE `unitPrice3` `unitPrice3` FLOAT NULL, CHANGE `unitPrice4` `unitPrice4` FLOAT NULL;

--update basecolor_v3
UPDATE basecolor_v3 SET unitPrice1 = NULL and unitPrice2 = NULL and unitPrice3 = NULL and unitPrice4 = NULL and kgLtrFlag = NULL;

-- Name rename
ALTER TABLE `fandecks_v3` CHANGE `name` `Name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- column rename in shadecolors_v3
ALTER TABLE `shadecolors_v3` CHANGE `colorcode` `colorCode` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, CHANGE `colorname` `colorName` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

-- rename shadecolors_v3
ALTER TABLE `shadecolors_v3` CHANGE `rvalue` `rValue` INT NULL DEFAULT NULL, CHANGE `gvalue` `gValue` INT NULL DEFAULT NULL, CHANGE `bvalue` `bValue` INT NULL DEFAULT NULL;

-- live tables drop
DROP TABLE `basecolor_v3`, `colorants_v3`, `fandecks_v3`, `royal_acrylic_washalbe_distemper`, `royal_glow_interior_luxury_emulsion`, `royal_protect_luxury_exterior_emulsion`, `royal_sleek_luxury_emulsion`, `shadecolors_v3`, `ultra_weather_clad_luxury_exterior_emulsion`, `velvet_touch_interior_permium_emulsion`, `weather_clad_luxury_exterior_emulsion`, `yatra_acrylic_washable_distemper`,`yatra_interior_emulsion`;
