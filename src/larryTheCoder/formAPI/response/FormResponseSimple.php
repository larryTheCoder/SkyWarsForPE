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

namespace larryTheCoder\formAPI\response;


use larryTheCoder\formAPI\element\ElementButton;
use larryTheCoder\formAPI\form\SimpleForm;

class FormResponseSimple extends FormResponse {

    /** @var SimpleForm */
    private $form;
    /** @var int */
    private $clickedButtonId;
    /** @var ElementButton[] */
    private $buttons;
    /** @var ElementButton */
    private $clickedButton;

    public function __construct(SimpleForm $form, array $buttons) {
        $this->form = $form;
        $this->buttons = $buttons;
    }

    /**
     * @return int
     */
    public function getClickedButtonId(): int {
        return $this->clickedButtonId;
    }

    /**
     * @return bool
     */
    public function isClosed(): bool {
        return $this->closed;
    }

    /**
     * Get the clicked button
     *
     * @return ElementButton
     */
    public function getClickedButton(): ElementButton {
        return $this->clickedButton;
    }

    /**
     * @param string $data
     */
    public function setData(string $data) {
        if ($data === "null") {
            $this->closed = true;
            return;
        }
        // It quite impossible if we sent a lot of data
        // Or button on this.
        $this->clickedButtonId = (int)$data;
        $this->clickedButton = $this->buttons[$data];
    }
}