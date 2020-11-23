<?php
/*
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
 *
 * Permission is hereby granted to any persons and/or organizations
 * using this software to copy, modify, merge, publish, and distribute it.
 * Said persons and/or organizations are not allowed to use the software or
 * any derivatives of the work for commercial use or any other means to generate
 * income, nor are they allowed to claim this software as their own.
 *
 * The persons and/or organizations are also disallowed from sub-licensing
 * and/or trademarking this software without explicit permission from larryTheCoder.
 *
 * Any persons and/or organizations using this software must disclose their
 * source code and have it publicly available, include this license,
 * provide sufficient credit to the original authors of the project (IE: larryTheCoder),
 * as well as provide a link to the original project.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
 * USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

declare(strict_types = 1);

namespace larryTheCoder\arena\api\translation;

use larryTheCoder\arena\api\utils\SingletonTrait;
use larryTheCoder\utils\Settings;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

/**
 * Translation container for the arena API class and whole framework.
 *
 * @package larryTheCoder\arena\translation
 */
class TranslationContainer {
	use SingletonTrait;

	/** @var Config[] */
	private $translation = [];

	/**
	 * Add a new translation image for the arena API functionalities.
	 *
	 * @param string $isoCodename This codename is CASE-SENSITIVE, please make sure that this is exactly the same as
	 *                            defined in {@link TranslationContainer::REGISTERED_CODENAME}
	 * @param Config $config The config file of the translation.
	 */
	public function addTranslation(string $isoCodename, Config $config): void{
		if(!in_array($isoCodename, self::REGISTERED_CODENAME, true)){
			Server::getInstance()->getLogger()->error("Translation container for locale $isoCodename is not available in minecraft!");

			return;
		}

		$this->translation[$isoCodename] = $config;
	}

	/**
	 * Retrieve the translation container for the given player.
	 *
	 * @param CommandSender|null $player
	 * @param string $key
	 * @param mixed[] $replacements The replacement keys and values, you can specify your own settings here.
	 * @return string If the given key was not found, the key will be returned.
	 */
	public static function getTranslation(?CommandSender $player, string $key, array $replacements = []): string{
		$keys = array_merge(["&", "%prefix"], array_keys($replacements));
		$values = array_merge(["§", Settings::$prefix], array_values($replacements));

		// Always retrieve default locale first
		$defaultLocale = self::getInstance()->translation["en_US"] ?? null;
		if($defaultLocale === null){
			Server::getInstance()->getLogger()->error(Settings::$prefix . TextFormat::RED . "ERROR: DEFAULT LOCALE COULD NOT BE LOCATED!");

			return "";
		}

		if(!is_null($player) && $player instanceof Player){
			$translation = self::getInstance()->translation[$player->getLocale()] ?? $defaultLocale;

			return str_replace($keys, $values, $translation->get($key, $defaultLocale->get($key, $key)));
		}else{
			return str_replace($keys, $values, $defaultLocale->get($key, $key));
		}
	}

	/**
	 * You can configure default locale here.
	 */
	public const DEFAULT_LOCALE = "en_US";

	/**
	 * ISO 639 (language) and ISO 3166-1 alpha-2 (2-letter country) codes defined by mojang.
	 */
	private const REGISTERED_CODENAME = [
		"en_US",
		"en_GB",
		"de_DE",
		"es_ES",
		"es_MX",
		"fr_FR",
		"fr_CA",
		"it_IT",
		"ja_JP",
		"ko_KR",
		"pt_BR",
		"pt_PT",
		"ru_RU",
		"zh_CN",
		"zh_TW",
		"nl_NL",
		"bg_BG",
		"cs_CZ",
		"da_DK",
		"el_GR",
		"fi_FI",
		"hu_HU",
		"id_ID",
		"nb_NO",
		"pl_PL",
		"sk_SK",
		"sv_SE",
		"tr_TR",
		"uk_UA",
	];
}