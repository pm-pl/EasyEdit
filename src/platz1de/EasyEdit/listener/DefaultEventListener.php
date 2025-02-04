<?php

namespace platz1de\EasyEdit\listener;

use platz1de\EasyEdit\brush\BrushHandler;
use platz1de\EasyEdit\command\KnownPermissions;
use platz1de\EasyEdit\EasyEdit;
use platz1de\EasyEdit\selection\Cube;
use platz1de\EasyEdit\task\editing\expanding\ExtendBlockFaceTask;
use platz1de\EasyEdit\utils\BlockInfoTool;
use platz1de\EasyEdit\utils\ConfigManager;
use platz1de\EasyEdit\world\clientblock\ClientSideBlockManager;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\Axe;
use pocketmine\item\BlazeRod;
use pocketmine\item\Shovel;
use pocketmine\item\Stick;
use pocketmine\item\TieredTool;
use pocketmine\item\ToolTier;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use Throwable;

class DefaultEventListener implements Listener
{
	use ToggleableEventListener;

	private static float $cooldown = 0;
	private const CREATIVE_REACH = 5;

	/**
	 * @param BlockBreakEvent $event
	 */
	public function onBreak(BlockBreakEvent $event): void
	{
		$axe = $event->getItem();
		if ($axe instanceof Axe && $axe->getTier() === ToolTier::WOOD() && $event->getPlayer()->isCreative() && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_SELECT)) {
			$event->cancel();
			Cube::selectPos1($event->getPlayer(), $event->getBlock()->getPosition());
		} elseif ($axe instanceof Stick && $axe->getNamedTag()->getByte("isInfoStick", 0) === 1) {
			$event->cancel();
			BlockInfoTool::display($event->getPlayer()->getName(), $event->getBlock());
		}

		self::$cooldown = microtime(true) + ConfigManager::getToolCooldown();
	}

	/**
	 * @param PlayerInteractEvent $event
	 */
	public function onInteract(PlayerInteractEvent $event): void
	{
		if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			if (self::$cooldown < microtime(true)) {
				self::$cooldown = microtime(true) + ConfigManager::getToolCooldown();
			} else {
				return;
			}

			$item = $event->getItem();
			if ($item instanceof TieredTool && $item->getTier() === ToolTier::WOOD() && $event->getPlayer()->isCreative()) {
				if ($item instanceof Axe && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_SELECT)) {
					$event->cancel();
					Cube::selectPos2($event->getPlayer(), $event->getBlock()->getPosition());
				} elseif ($item instanceof Shovel && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_BRUSH) && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_EDIT)) {
					$event->cancel();
					BrushHandler::handleBrush($item->getNamedTag(), $event->getPlayer());
				}
			} elseif ($item instanceof BlazeRod && $item->getNamedTag()->getByte("isBuilderRod", 0) === 1 && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_EDIT) && $event->getPlayer()->isCreative()) {
				$event->cancel();
				ExtendBlockFaceTask::queue($event->getPlayer()->getName(), $event->getPlayer()->getWorld()->getFolderName(), $event->getBlock()->getPosition(), $event->getFace());
			}
		}
	}

	/**
	 * Interaction with air
	 * @param PlayerItemUseEvent $event
	 */
	public function onUse(PlayerItemUseEvent $event): void
	{
		try {
			$block = $event->getPlayer()->getTargetBlock(self::CREATIVE_REACH, [BlockLegacyIds::STILL_WATER => true, BlockLegacyIds::FLOWING_WATER => true, BlockLegacyIds::STILL_LAVA => true, BlockLegacyIds::FLOWING_LAVA => true, BlockLegacyIds::AIR => true]);
		} catch (Throwable) {
			//No idea why this is crashing for some users, probably caused by weird binaries / plugins
			EasyEdit::getInstance()->getLogger()->warning("Player " . $event->getPlayer()->getName() . " has thrown an exception while trying to get a target block");
			return;
		}
		$item = $event->getItem();
		if ($block === null || in_array($block->getId(), [BlockLegacyIds::STILL_WATER, BlockLegacyIds::FLOWING_WATER, BlockLegacyIds::STILL_LAVA, BlockLegacyIds::FLOWING_LAVA, BlockLegacyIds::AIR], true)) {
			if ($item instanceof TieredTool && $item->getTier() === ToolTier::WOOD() && $event->getPlayer()->isCreative()) {
				if ($item instanceof Axe && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_SELECT)) {
					$event->cancel();
					try {
						$target = $event->getPlayer()->getTargetBlock(100, [BlockLegacyIds::STILL_WATER => true, BlockLegacyIds::FLOWING_WATER => true, BlockLegacyIds::STILL_LAVA => true, BlockLegacyIds::FLOWING_LAVA => true, BlockLegacyIds::AIR => true]);
					} catch (Throwable) {
						//No idea why this is crashing for some users, probably caused by weird binaries / plugins
						EasyEdit::getInstance()->getLogger()->warning("Player " . $event->getPlayer()->getName() . " has thrown an exception while trying to get a target block");
						return;
					}
					if ($target instanceof Block) {
						//HACK: Touch control sends Itemuse when starting to break a block
						//This gets triggered when breaking a block which isn't focused
						EasyEdit::getInstance()->getScheduler()->scheduleTask(new ClosureTask(function () use ($target, $event): void {
							if (self::$cooldown < microtime(true)) {
								Cube::selectPos2($event->getPlayer(), $target->getPosition());
							}
						}));
					}
				} elseif ($item instanceof Shovel && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_BRUSH) && $event->getPlayer()->hasPermission(KnownPermissions::PERMISSION_EDIT)) {
					$event->cancel();
					BrushHandler::handleBrush($item->getNamedTag(), $event->getPlayer());
				}
			}
		} elseif ($item instanceof Stick && $item->getNamedTag()->getByte("isInfoStick", 0) === 1) {
			$event->cancel();
			//We use a raytrace here as some blocks can't be interacted with
			BlockInfoTool::display($event->getPlayer()->getName(), $block);
		}
	}

	public function onWorldChange(EntityTeleportEvent $event): void
	{
		$player = $event->getEntity();
		if ($player instanceof Player) {
			$playerName = $player->getName();
			EasyEdit::getInstance()->getScheduler()->scheduleTask(new ClosureTask(function () use ($playerName): void {
				ClientSideBlockManager::resendAll($playerName);
			}));
		}
	}

	public function onJoin(PlayerJoinEvent $event): void
	{
		ClientSideBlockManager::resendAll($event->getPlayer()->getName());
	}

	public function onMove(PlayerMoveEvent $event): void
	{
		ClientSideBlockManager::updateAll($event->getPlayer());
	}
}