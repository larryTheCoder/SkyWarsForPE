<?php
declare(strict_types = 1);

namespace larryTheCoder\forms\elements;

class Button extends Element {
	/** @var Image|null */
	protected $image;
	/** @var string */
	protected $type;

	/**
	 * @param string $text
	 * @param Image|null $image
	 */
	public function __construct(string $text, ?Image $image = null){
		parent::__construct($text);
		$this->image = $image;
	}

	/**
	 * @param string ...$texts
	 *
	 * @return Button[]
	 */
	public static function createFromList(string ...$texts): array{
		$buttons = [];
		foreach($texts as $text){
			$buttons[] = new self($text);
		}

		return $buttons;
	}

	/**
	 * @return string|null
	 */
	public function getType(): ?string{
		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function serializeElementData(): array{
		$data = ["text" => $this->text];
		if($this->hasImage()){
			$data["image"] = $this->image;
		}

		return $data;
	}

	/**
	 * @return bool
	 */
	public function hasImage(): bool{
		return $this->image !== null;
	}
}