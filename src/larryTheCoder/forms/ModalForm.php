<?php

declare(strict_types = 1);

namespace larryTheCoder\forms;

use Closure;
use pocketmine\{form\FormValidationException, Player, utils\Utils};
use function gettype;
use function is_bool;

class ModalForm extends Form {
	/** @var string */
	protected $text;
	/** @var string */
	private $yesButton;
	/** @var string */
	private $noButton;
	/** @var Closure */
	private $onSubmit;

	/**
	 * @param string $title
	 * @param string $text
	 * @param Closure $onSubmit
	 * @param string $yesButton
	 * @param string $noButton
	 */
	public function __construct(string $title, string $text, Closure $onSubmit, string $yesButton = "gui.yes", string $noButton = "gui.no"){
		parent::__construct($title);
		$this->text = $text;
		$this->yesButton = $yesButton;
		$this->noButton = $noButton;
		Utils::validateCallableSignature(function(Player $player, bool $response): void{
		}, $onSubmit);
		$this->onSubmit = $onSubmit;
	}

	/**
	 * @param string $title
	 * @param string $text
	 * @param Closure $onConfirm
	 *
	 * @return ModalForm
	 */
	public static function createConfirmForm(string $title, string $text, Closure $onConfirm): self{
		Utils::validateCallableSignature(function(Player $player): void{
		}, $onConfirm);

		return new self($title, $text, function(Player $player, bool $response) use ($onConfirm): void{
			if($response){
				$onConfirm($player);
			}
		});
	}

	/**
	 * @return string
	 */
	final public function getType(): string{
		return self::TYPE_MODAL;
	}

	/**
	 * @return string
	 */
	public function getYesButtonText(): string{
		return $this->yesButton;
	}

	/**
	 * @return string
	 */
	public function getNoButtonText(): string{
		return $this->noButton;
	}

	final public function handleResponse(Player $player, $data): void{
		if(!is_bool($data)){
			throw new FormValidationException("Expected bool, got " . gettype($data));
		}
		($this->onSubmit)($player, $data);
	}

	/**
	 * @return array
	 */
	protected function serializeFormData(): array{
		return [
			"content" => $this->text,
			"button1" => $this->yesButton,
			"button2" => $this->noButton,
		];
	}
}