<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
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


namespace larryTheCoder\panel;

use larryTheCoder\arena\ArenaImpl;

class SkyWarsData {
	/** @var string */
	public $arenaName = "";
	/** @var string */
	public $arenaLevel = "";
	/** @var int */
	public $maxPlayer = 12;
	/** @var int */
	public $minPlayer = 4;
	/** @var bool */
	public $spectator = false;
	/** @var bool */
	public $startWhenFull = false;
	/** @var int */
	public $graceTimer = 10;
	/** @var bool */
	public $enabled = false;
	/** @var string */
	public $line1 = "";
	/** @var string */
	public $line2 = "";
	/** @var string */
	public $line3 = "";
	/** @var string */
	public $line4 = "";
	/** @var ArenaImpl */
	public $arena;
}