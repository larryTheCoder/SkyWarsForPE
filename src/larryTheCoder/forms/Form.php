<?php

declare(strict_types = 1);

namespace larryTheCoder\forms;

use Closure;
use function array_merge;

abstract class Form implements \pocketmine\form\Form {
	protected const TYPE_MODAL = "modal";
	protected const TYPE_MENU = "form";
	protected const TYPE_CUSTOM_FORM = "custom_form";
	/** @var string */
	private $title;
	/** @var Closure|null */
	private $onCreate;
	/** @var Closure|null */
	private $onDestroy;

	/**
	 * @param string $title
	 */
	public function __construct(string $title){
		$this->title = $title;
	}

	public function __destruct(){
		if($this->onDestroy !== null){
			($this->onDestroy)();
		}
	}

	/**
	 * @return array
	 */
	final public function jsonSerialize(): array{
		if($this->onCreate !== null){
			($this->onCreate)();
		}

		return array_merge([
			"title" => $this->getTitle(), "type" => $this->getType(),
		], $this->serializeFormData());
	}

	/**
	 * @return string
	 */
	public function getTitle(): string{
		return $this->title;
	}

	/**
	 * @param string $title
	 *
	 * @return $this
	 */
	public function setTitle(string $title): self{
		$this->title = $title;

		return $this;
	}

	/**
	 * @return string
	 */
	abstract public function getType(): string;

	/**
	 * @return array
	 */
	abstract protected function serializeFormData(): array;

	/**
	 * @param Closure $onCreate
	 *
	 * @return $this
	 */
	public function setOnCreate(Closure $onCreate): self{
		$this->onCreate = $onCreate;

		return $this;
	}

	/**
	 * @param Closure $onDestroy
	 *
	 * @return $this
	 */
	public function setOnDestroy(Closure $onDestroy): self{
		$this->onDestroy = $onDestroy;

		return $this;
	}
}