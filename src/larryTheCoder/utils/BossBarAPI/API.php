<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2019 larryTheCoder and contributors
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

namespace larryTheCoder\utils\BossBarAPI;

use pocketmine\{Player, Server};
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\{AddActorPacket,
	BossEventPacket,
	RemoveActorPacket,
	SetActorDataPacket,
	UpdateAttributesPacket};

class API {

	const ENTITY = 37;//52 - 37 is slime, inspired by MiNET

	/**
	 * Sends the text to all players
	 *
	 * @param Player[] $players
	 * To who to send
	 * @param string $title
	 * The title of the boss bar
	 * How long it displays
	 * @return int EntityID NEEDED FOR CHANGING TEXT/PERCENTAGE! | null (No Players)
	 */
	public static function addBossBar($players, string $title){
		if(empty($players)) return null;

		$eid = Entity::$entityCount++;

		$packet = new AddActorPacket();
		$packet->entityRuntimeId = $eid;
		$packet->type = self::ENTITY;
		$packet->metadata = [Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1], Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 0 ^ 1 << Entity::DATA_FLAG_SILENT ^ 1 << Entity::DATA_FLAG_INVISIBLE ^ 1 << Entity::DATA_FLAG_NO_AI], Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0],
							 Entity::DATA_NAMETAG         => [Entity::DATA_TYPE_STRING, $title], Entity::DATA_BOUNDING_BOX_WIDTH => [Entity::DATA_TYPE_FLOAT, 0], Entity::DATA_BOUNDING_BOX_HEIGHT => [Entity::DATA_TYPE_FLOAT, 0]];
		foreach($players as $player){
			$pk = clone $packet;
			$pk->position = $player->getPosition()->asVector3()->subtract(0, 28);
			$player->dataPacket($pk);
		}

		$bpk = new BossEventPacket(); // This updates the bar
		$bpk->bossEid = $eid;
		$bpk->eventType = BossEventPacket::TYPE_SHOW;
		$bpk->title = $title;
		$bpk->healthPercent = 1;
		$bpk->unknownShort = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->color = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->overlay = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->playerEid = 0;//TODO TEST!!!
		Server::getInstance()->broadcastPacket($players, $bpk);

		return $eid; // TODO: return EID from bosseventpacket?
	}

	/**
	 * Sends the text to one player
	 *
	 * @param Player $player
	 * @param int $eid
	 * The EID of an existing fake wither
	 * @param string $title
	 * The title of the boss bar
	 * How long it displays
	 * @internal param Player $players To who to send* To who to send
	 */
	public static function sendBossBarToPlayer(Player $player, int $eid, string $title){
		self::removeBossBar([$player], $eid);//remove same bars

		$packet = new AddActorPacket();
		$packet->entityRuntimeId = $eid;
		$packet->type = self::ENTITY;
		$packet->position = $player->getPosition()->asVector3()->subtract(0, 28);
		$packet->metadata = [Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1], Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 0 ^ 1 << Entity::DATA_FLAG_SILENT ^ 1 << Entity::DATA_FLAG_INVISIBLE ^ 1 << Entity::DATA_FLAG_NO_AI], Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0],
							 Entity::DATA_NAMETAG         => [Entity::DATA_TYPE_STRING, $title], Entity::DATA_BOUNDING_BOX_WIDTH => [Entity::DATA_TYPE_FLOAT, 0], Entity::DATA_BOUNDING_BOX_HEIGHT => [Entity::DATA_TYPE_FLOAT, 0]];
		$player->dataPacket($packet);

		$bpk = new BossEventPacket(); // This updates the bar. According to shoghi this should not even be needed, but #blameshoghi, it doesn't update without
		$bpk->bossEid = $eid;
		$bpk->eventType = BossEventPacket::TYPE_SHOW;
		$bpk->title = $title;
		$bpk->healthPercent = 1;
		$bpk->unknownShort = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->color = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->overlay = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->playerEid = 0;//TODO TEST!!!
		$player->dataPacket($bpk);
	}

	/**
	 * Remove BossBar from players by EID
	 *
	 * @param Player[] $players
	 * @param int $eid
	 * @return boolean removed
	 */
	public static function removeBossBar($players, int $eid){
		if(empty($players)) return false;

		$pk = new RemoveActorPacket();
		$pk->entityUniqueId = $eid;
		Server::getInstance()->broadcastPacket($players, $pk);

		return true;
	}

	/**
	 * Sets how many % the bar is full by EID
	 *
	 * @param int $percentage
	 * 0-100
	 * @param int $eid
	 * @param array $players
	 * If empty this will default to Server::getInstance()->getOnlinePlayers()
	 */
	public static function setPercentage(int $percentage, int $eid, $players = []){
		if(empty($players)) $players = Server::getInstance()->getOnlinePlayers();
		if(!count($players) > 0) return;

		$upk = new UpdateAttributesPacket(); // Change health of fake wither -> bar progress
		$upk->entries[] = new BossBarValues(1, 600, max(1, min([$percentage, 100])) / 100 * 600, 'minecraft:health'); // Ensures that the number is between 1 and 100; //Blame mojang, Ender Dragon seems to die on health 1
		$upk->entityRuntimeId = $eid;
		Server::getInstance()->broadcastPacket($players, $upk);

		$bpk = new BossEventPacket(); // This updates the bar
		$bpk->bossEid = $eid;
		$bpk->eventType = BossEventPacket::TYPE_SHOW;
		$bpk->title = ""; //We can't get this -.-
		$bpk->healthPercent = $percentage / 100;
		$bpk->unknownShort = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->color = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->overlay = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->playerEid = 0;//TODO TEST!!!
		Server::getInstance()->broadcastPacket($players, $bpk);
	}

	/**
	 * Sets the BossBar title by EID
	 *
	 * @param string $title
	 * @param int $eid
	 * @param Player[] $players
	 */
	public static function setTitle(string $title, int $eid, $players = []){
		if(!count(Server::getInstance()->getOnlinePlayers()) > 0) return;

		$npk = new SetActorDataPacket(); // change name of fake wither -> bar text
		$npk->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $title]];
		$npk->entityRuntimeId = $eid;
		Server::getInstance()->broadcastPacket($players, $npk);

		$bpk = new BossEventPacket(); // This updates the bar
		$bpk->bossEid = $eid;
		$bpk->eventType = BossEventPacket::TYPE_SHOW;
		$bpk->title = $title;
		$bpk->healthPercent = 1;
		$bpk->unknownShort = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->color = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->overlay = 0;//TODO: remove. Shoghi deleted that unneeded mess that was copy-pasted from MC-JAVA
		$bpk->playerEid = 0;//TODO TEST!!!
		Server::getInstance()->broadcastPacket($players, $bpk);
	}
}