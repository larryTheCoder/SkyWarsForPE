<?php

declare(strict_types = 1);

namespace larryTheCoder\forms;

use Closure;
use larryTheCoder\forms\elements\Button;
use pocketmine\{form\FormValidationException, Player, utils\Utils};
use function array_merge;
use function is_string;

class MenuForm extends Form {
	/** @var Button[] */
	protected $buttons = [];
	/** @var string */
	protected $text;
	/** @var Closure|null */
	private $onSubmit;
	/** @var Closure|null */
	private $onClose;

	/**
	 * @param string $title
	 * @param string $text
	 * @param Button|string[] $buttons
	 * @param Closure|null $onSubmit
	 * @param Closure|null $onClose
	 */
	public function __construct(string $title, string $text = "", array $buttons = [], ?Closure $onSubmit = null, ?Closure $onClose = null){
		parent::__construct($title);
		$this->text = $text;
		$this->append(...$buttons);
		$this->setOnSubmit($onSubmit);
		$this->setOnClose($onClose);
	}

	/**
	 * @param Button|string ...$buttons
	 *
	 * @return self
	 */
	public function append(...$buttons): self{
		if(isset($buttons[0]) && is_string($buttons[0])){
			$buttons = Button::createFromList(...$buttons);
		}
		$this->buttons = array_merge($this->buttons, $buttons);

		return $this;
	}

	/**
	 * @param Closure|null $onSubmit
	 *
	 * @return self
	 */
	public function setOnSubmit(?Closure $onSubmit): self{
		if($onSubmit !== null){
			Utils::validateCallableSignature(function(Player $player, Button $selected): void{
			}, $onSubmit);
			$this->onSubmit = $onSubmit;
		}

		return $this;
	}

	/**
	 * @param Closure|null $onClose
	 *
	 * @return self
	 */
	public function setOnClose(?Closure $onClose): self{
		if($onClose !== null){
			Utils::validateCallableSignature(function(Player $player): void{
			}, $onClose);
			$this->onClose = $onClose;
		}

		return $this;
	}

	/**
	 * @param string $text
	 *
	 * @return self
	 */
	public function setText(string $text): self{
		$this->text = $text;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getType(): string{
		return self::TYPE_MENU;
	}

	final public function handleResponse(Player $player, $data): void{
		if($data === null){
			if($this->onClose !== null){
				($this->onClose)($player, $data);
			}
		}elseif(is_int($data)){
			if(!isset($this->buttons[$data])){
				throw new FormValidationException("Button with index $data does not exist");
			}
			if($this->onSubmit !== null){
				$button = $this->buttons[$data];
				$button->setValue($data);
				($this->onSubmit)($player, $button);
			}
		}else{
			throw new FormValidationException("Expected int or null, got " . gettype($data));
		}
	}

	/**
	 * @return array
	 */
	protected function serializeFormData(): array{
		return [
			"buttons" => $this->buttons,
			"content" => $this->text,
		];
	}
}