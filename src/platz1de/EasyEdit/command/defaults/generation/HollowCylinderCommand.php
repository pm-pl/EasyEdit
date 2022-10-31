<?php

namespace platz1de\EasyEdit\command\defaults\generation;

use platz1de\EasyEdit\command\flags\CommandFlag;
use platz1de\EasyEdit\command\flags\CommandFlagCollection;
use platz1de\EasyEdit\command\flags\FloatCommandFlag;
use platz1de\EasyEdit\command\flags\IntegerCommandFlag;
use platz1de\EasyEdit\command\flags\PatternCommandFlag;
use platz1de\EasyEdit\command\KnownPermissions;
use platz1de\EasyEdit\command\SimpleFlagArgumentCommand;
use platz1de\EasyEdit\pattern\logic\selection\SidesPattern;
use platz1de\EasyEdit\selection\Cylinder;
use platz1de\EasyEdit\session\Session;
use platz1de\EasyEdit\task\editing\selection\pattern\SetTask;

class HollowCylinderCommand extends SimpleFlagArgumentCommand
{
	public function __construct()
	{
		parent::__construct("/hcylinder", ["radius" => true, "height" => true, "pattern" => true, "thickness" => false], [KnownPermissions::PERMISSION_GENERATE, KnownPermissions::PERMISSION_EDIT], ["/hcy", "/hollowcylinder"]);
	}

	/**
	 * @param Session               $session
	 * @param CommandFlagCollection $flags
	 */
	public function process(Session $session, CommandFlagCollection $flags): void
	{
		if (!$flags->hasFlag("thickness")) {
			$flags->addFlag(FloatCommandFlag::with(1.0, "thickness"));
		}
		$session->runTask(new SetTask(Cylinder::aroundPoint($session->asPlayer()->getWorld()->getFolderName(), $session->asPlayer()->getPosition(), $flags->getFloatFlag("radius"), $flags->getIntFlag("height")), new SidesPattern($flags->getFloatFlag("thickness"), [$flags->getPatternFlag("pattern")])));
	}

	/**
	 * @param Session $session
	 * @return CommandFlag[]
	 */
	public function getKnownFlags(Session $session): array
	{
		return [
			"radius" => new FloatCommandFlag("radius", ["rad"], "r"),
			"height" => new IntegerCommandFlag("height", [], "h"),
			"pattern" => new PatternCommandFlag("pattern", [], "p"),
			"thickness" => new FloatCommandFlag("thickness", ["thick"], "t")
		];
	}
}