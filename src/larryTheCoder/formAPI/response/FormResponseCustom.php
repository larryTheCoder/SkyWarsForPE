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

use larryTheCoder\formAPI\element\{
    ElementDropdown, ElementInput, ElementLabel, ElementSlider, ElementStepSlider, ElementToggle
};
use larryTheCoder\formAPI\form\CustomForm;

class FormResponseCustom extends FormResponse {

    /** @var array */
    private $responses = [];
    /** @var FormResponseData[] */
    private $dropdownResponses = [];
    /** @var string[] */
    private $inputResponses = [];
    /** @var integer[] */
    private $sliderResponses = [];
    /** @var FormResponseData[] */
    private $stepSliderResponses = [];
    /** @var boolean[] */
    private $toggleResponses = [];
    /** @var CustomForm */
    private $form;

    public function __construct(CustomForm $form) {
        $this->form = $form;
    }

    /**
     * @return array
     */
    public function getResponses(): array {
        return $this->responses;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function getResponse(int $id) {
        return $this->responses[$id];
    }

    /**
     * @param int $id
     * @return FormResponseData
     */
    public function getDropdownResponse(int $id): FormResponseData {
        return $this->dropdownResponses[$id];
    }

    /**
     * @param int $id
     * @return string
     */
    public function getInputResponse(int $id): string {
        return $this->inputResponses[$id];
    }

    /**
     * @param int $id
     * @return int
     */
    public function getSliderResponse(int $id): int {
        return $this->sliderResponses[$id];
    }

    /**
     * @param int $id
     * @return FormResponseData
     */
    public function getStepSliderResponse(int $id): FormResponseData {
        return $this->stepSliderResponses[$id];
    }

    /**
     * @param int $id
     * @return bool
     */
    public function getToggleResponse(int $id): bool {
        return $this->toggleResponses[$id];
    }

    public function setData(string $data) {
        if ($data === "null") {
            $this->closed = true;
            return;
        }
        $json = json_decode($data);

        $dropdownResponses = [];
        $inputResponses = [];
        $sliderResponses = [];
        $stepSliderResponses = [];
        $toggleResponses = [];
        $responses = [];

        $i = 0;
        $contents = $this->form->elements;
        foreach ($json as $elementData) {
            if ($i >= count($this->form->elements)) {
                break;
            }
            $e = $contents[$i];
            if ($e === null) break;
            if ($e instanceof ElementLabel) {
                $i++;
                continue;
            }
            if ($e instanceof ElementDropdown) {
                $answer = $e->getOptions()[intval($elementData)];
                $dropdownResponses[$i] = new FormResponseData(intval($elementData), $answer);
                $responses[$i] = $answer;
            } else if ($e instanceof ElementInput) {
                $inputResponses[$i] = $elementData;
                $responses[$i] = $elementData;
            } else if ($e instanceof ElementSlider) {
                $sliderResponses[$i] = $elementData;
                $responses[$i] = $elementData;
            } else if ($e instanceof ElementStepSlider) {
                $answer = $e->getSteps()[intval($elementData)];
                $stepSliderResponses[$i] = new FormResponseData(intval($elementData), $answer);
                $responses[$i] = $answer;
            } else if ($e instanceof ElementToggle) {
                $answer = boolval($elementData);
                $toggleResponses[$i] = $answer;
                $responses[$i] = $answer;
            }
            $i++;
        }

        $this->dropdownResponses = $dropdownResponses;
        $this->inputResponses = $inputResponses;
        $this->sliderResponses = $sliderResponses;
        $this->stepSliderResponses = $stepSliderResponses;
        $this->toggleResponses = $toggleResponses;
        $this->responses = $responses;
    }
}