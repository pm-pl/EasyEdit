<?php

namespace platz1de\EasyEdit\schematic;

use JsonException;
use platz1de\EasyEdit\selection\BlockListSelection;
use platz1de\EasyEdit\thread\EditThread;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\Chest;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Hopper;
use pocketmine\block\tile\ShulkerBox;
use pocketmine\block\tile\Sign;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\nbt\tag\CompoundTag;
use Throwable;
use UnexpectedValueException;

class TileConvertor
{
	public const DATA_CHEST_RELATION = "chest_relation";
	public const DATA_SHULKER_BOX_FACING = "shulker_box_facing";

	public const TILE_CHEST = "minecraft:chest";
	public const TILE_DISPENSER = "minecraft:dispenser";
	public const TILE_DROPPER = "minecraft:dropper";
	public const TILE_HOPPER = "minecraft:hopper";
	public const TILE_SHULKER_BOX = "minecraft:shulker_box";
	public const TILE_SIGN = "minecraft:sign";
	public const TILE_TRAPPED_CHEST = "minecraft:trapped_chest";

	/**
	 * TODO: Add all of the tiles underneath
	 * Beehive
	 * Bee Nest
	 * Banners
	 * Furnace
	 * Brewing Stand
	 * Barrel
	 * Smoker
	 * Blast Furnace
	 * Campfire
	 * Soul Campfire
	 * Lectern
	 * Beacon
	 * Spawner
	 * Note Block (blockstate in java)
	 * Piston -> Moving Piston
	 * Jukebox
	 * Enchanting Table
	 * End Portal
	 * Ender Chest
	 * Mob Head
	 * Command Block
	 * End Gateway
	 * Structure Block
	 * Jigsaw Block
	 * Nether Reactor Core
	 * Daylight Sensor
	 * Flower Pot (blockstate in java)
	 * Redstone Comparator
	 * Bed
	 * Cauldron (blockstate in java)
	 * Conduit
	 * Bell
	 * Lodestone
	 *
	 * Item Frame (entity in java)
	 */

	/**
	 * @param CompoundTag        $tile
	 * @param BlockListSelection $selection
	 * @param CompoundTag|null   $extraData
	 */
	public static function toBedrock(CompoundTag $tile, BlockListSelection $selection, ?CompoundTag $extraData): void
	{
		//some of these aren't actually part of pmmp yet, but plugins might use them
		if ($extraData !== null) {
			foreach ($extraData->getValue() as $key => $value) {
				$tile->setTag($key, $value);
			}
		}
		try {
			switch ($tile->getString(Tile::TAG_ID)) {
				case self::TILE_SIGN:
					//TODO: glowing & color
					for ($i = 1; $i <= 4; $i++) {
						$line = $tile->getString("Text" . $i);
						try {
							/** @var string[] $json */
							$json = json_decode($line, true, 2, JSON_THROW_ON_ERROR);
							if (!isset($json["text"])) {
								throw new JsonException("Missing text key");
							}
							$text = $json["text"];
						} catch (JsonException) {
							throw new UnexpectedValueException("Invalid JSON in sign text: " . $line);
						}
						$tile->setString("Text" . $i, $text);
					}
					break;
				/** @noinspection PhpMissingBreakStatementInspection */
				case self::TILE_TRAPPED_CHEST:
					$tile->setString(Tile::TAG_ID, self::TILE_CHEST); //pmmp uses the same tile here
				case self::TILE_CHEST:
					self::convertItemsBedrock($tile);
					if (isset($tile->getValue()[Chest::TAG_PAIRX], $tile->getValue()[Chest::TAG_PAIRZ])) {
						$tile->setInt(Chest::TAG_PAIRX, $tile->getInt(Chest::TAG_PAIRX) + $tile->getInt(Tile::TAG_X));
						$tile->setInt(Chest::TAG_PAIRZ, $tile->getInt(Chest::TAG_PAIRZ) + $tile->getInt(Tile::TAG_Z));
					}
					break;
				case self::TILE_SHULKER_BOX:
				case self::TILE_DISPENSER:
				case self::TILE_DROPPER:
				case self::TILE_HOPPER:
					self::convertItemsBedrock($tile);
					break;
				default:
					EditThread::getInstance()->debug("Found unknown tile " . $tile->getString(Tile::TAG_ID));
					return;
			}
		} catch (Throwable $exception) {
			EditThread::getInstance()->debug("Found malformed tile " . $tile->getString(Tile::TAG_ID) . ": " . $exception->getMessage());
			return;
		}
		$selection->addTile($tile);
	}

	/**
	 * @param int         $blockId
	 * @param CompoundTag $tile
	 * @return bool
	 */
	public static function toJava(int $blockId, CompoundTag $tile): bool
	{
		$tile->setString(Tile::TAG_ID, self::getJavaId($tile->getString(Tile::TAG_ID)));
		switch ($tile->getString(Tile::TAG_ID)) {
			case self::TILE_SIGN:
				//TODO: glowing & color
				for ($i = 1; $i <= 4; $i++) {
					$line = $tile->getString("Text" . $i);
					try {
						/** @var string $json */
						$json = json_encode(["text" => $line], JSON_THROW_ON_ERROR);
					} catch (JsonException) {
						throw new UnexpectedValueException("Failed to encode JSON for sign text: " . $line);
					}
					$tile->setString("Text" . $i, $json);
					$tile->removeTag(Sign::TAG_TEXT_BLOB);
				}
				break;
			case self::TILE_CHEST:
				if ($blockId >> Block::INTERNAL_METADATA_BITS === BlockLegacyIds::TRAPPED_CHEST) {
					$tile->setString(Tile::TAG_ID, self::TILE_TRAPPED_CHEST); //pmmp uses the same tile here
				}
				self::convertItemsJava($tile);
				if (isset($tile->getValue()[Chest::TAG_PAIRX], $tile->getValue()[Chest::TAG_PAIRZ])) {
					$tile->setInt(Chest::TAG_PAIRX, $tile->getInt(Chest::TAG_PAIRX) - $tile->getInt(Tile::TAG_X));
					$tile->setInt(Chest::TAG_PAIRZ, $tile->getInt(Chest::TAG_PAIRZ) - $tile->getInt(Tile::TAG_Z));
				}
				break;
			case self::TILE_SHULKER_BOX:
			case self::TILE_DISPENSER:
			case self::TILE_DROPPER:
			case self::TILE_HOPPER:
				self::convertItemsJava($tile);
				break;
			default:
				EditThread::getInstance()->debug("Found unknown tile " . $tile->getString(Tile::TAG_ID));
				return false;
		}
		return true;
	}

	/**
	 * @param string $tile
	 * @return string
	 */
	public static function getJavaId(string $tile): string
	{
		return match ($tile) {
			TileFactory::getInstance()->getSaveId(Chest::class) => self::TILE_CHEST,
			"Dispenser" => self::TILE_DISPENSER,
			"Dropper" => self::TILE_DROPPER,
			TileFactory::getInstance()->getSaveId(Hopper::class) => self::TILE_HOPPER,
			TileFactory::getInstance()->getSaveId(ShulkerBox::class) => self::TILE_SHULKER_BOX,
			TileFactory::getInstance()->getSaveId(Sign::class) => self::TILE_SIGN,
			default => $tile //just attempt it
		};
	}

	/**
	 * @param CompoundTag $tile
	 * @return void
	 */
	public static function convertItemsBedrock(CompoundTag $tile): void
	{
		//TODO: special data (lore...)
		$items = $tile->getListTag(Container::TAG_ITEMS);
		if ($items === null) {
			return;
		}
		foreach ($items as $item) {
			if (!$item instanceof CompoundTag) {
				throw new UnexpectedValueException("Items need to be represented as compound tags");
			}
			try {
				$javaId = $item->getString("id");
			} catch (Throwable) {
				continue; //probably already bedrock format, or at least not convertable
			}
			$i = BlockConvertor::getTranslatedItemBedrock($javaId);
			if ($i === null) {
				EditThread::getInstance()->debug("Couldn't convert item " . $javaId);
				continue;
			}
			$item->setShort("id", $i[0]);
			$item->setShort("Damage", $i[1]);
		}
	}

	/**
	 * @param CompoundTag $tile
	 * @return void
	 */
	public static function convertItemsJava(CompoundTag $tile): void
	{
		$items = $tile->getListTag(Container::TAG_ITEMS);
		if ($items === null) {
			return;
		}
		foreach ($items as $item) {
			if (!$item instanceof CompoundTag) {
				throw new UnexpectedValueException("Items need to be represented as compound tags");
			}
			$i = BlockConvertor::getTranslatedItemJava($item->getShort("id"), $item->getShort("Damage"));
			if ($i === null) {
				EditThread::getInstance()->debug("Couldn't convert item " . $item->getShort("id") . ":" . $item->getShort("Damage"));
				continue;
			}
			$item->removeTag("Damage");
			$item->setString("id", $i);
		}
	}
}