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
	 * @return mixed[]
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

	/**
	 * @return string
	 */
	abstract public function getType(): string;

	/**
	 * @return mixed[]
	 */
	abstract protected function serializeFormData(): array;
}