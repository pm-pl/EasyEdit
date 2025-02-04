<?php

namespace platz1de\EasyEdit\command\defaults\selection;

use platz1de\EasyEdit\command\exception\PatternParseException;
use platz1de\EasyEdit\pattern\block\SolidBlock;
use platz1de\EasyEdit\pattern\logic\relation\BlockPattern;
use platz1de\EasyEdit\pattern\parser\ParseError;
use platz1de\EasyEdit\pattern\parser\PatternParser;
use platz1de\EasyEdit\pattern\Pattern;
use platz1de\EasyEdit\utils\ArgumentParser;
use pocketmine\player\Player;

class ReplaceCommand extends AliasedPatternCommand
{
	public function __construct()
	{
		parent::__construct("/replace");
	}

	/**
	 * @param Player   $player
	 * @param string[] $args
	 * @return Pattern
	 */
	public function parsePattern(Player $player, array $args): Pattern
	{
		ArgumentParser::requireArgumentCount($args, 1, $this);
		if (count($args) >= 2) {
			try {
				$block = PatternParser::getBlockType($args[0]);
			} catch (ParseError $exception) {
				throw new PatternParseException($exception);
			}
			return new BlockPattern($block, [ArgumentParser::parseCombinedPattern($player, $args, 1)]);
		}
		return new BlockPattern(new SolidBlock(), [ArgumentParser::parseCombinedPattern($player, $args, 0)]);
	}
}