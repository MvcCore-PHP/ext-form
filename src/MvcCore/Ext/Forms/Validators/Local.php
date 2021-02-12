<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view 
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flidr (https://github.com/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/5.0.0/LICENSE.md
 */

namespace MvcCore\Ext\Forms\Validators;

/**
 * Responsibility: Short class to call any local valiation method, 
 *                 in form context, controller context or model 
 *                 instance context in model form.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Local extends \MvcCore\Ext\Forms\Validator {
	
	/**
	 * Error message index(es).
	 * @var int
	 */
	const ERROR_LOCAL_VALIDATION	= 0;

	/**
	 * Validation failure message template definitions.
	 * @var array
	 */
	protected static $errorMessages = [
		self::ERROR_LOCAL_VALIDATION	=> "Local validation error (field `{0}`).",
	];

	/**
	 * Method name to simply validate field value in some of already created classes.
	 * @var string
	 */
	protected $method;
	
	/**
	 * Validation method context definition, where the method is located, you can use constants:
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM_STATIC`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL_STATIC`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL_STATIC`
	 * Last two constants are usefull only for `mvccore/ext-model-form` extension.
	 * @var int
	 */
	protected $context = \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM;

	/**
	 * PHP reflection method instance.
	 * @var \ReflectionMethod|NULL
	 */
	protected $reflectionMethod = NULL;

	/**
	 * Reflection method context for instance method, NULL for static method.
	 * @var \MvcCore\Ext\Form|\MvcCore\Controller|\MvcCore\Ext\ModelForms\Form|NULL
	 */
	protected $reflectionInvokeObject = NULL;

	/**
	 * Set method name to simply validate field value in some of already created classes.
	 * @param string $methodName 
	 * @return \MvcCore\Ext\Forms\Validators\Local
	 */
	public function SetMethod ($methodName) {
		$this->method = $methodName;
		return $this;
	}
	
	/**
	 * Set validation method context definition, where the method is located, you can use constants:
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM_STATIC`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL_STATIC`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL`
	 *  - `\MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL_STATIC`
	 * Last two constants are usefull only for `mvccore/ext-model-form` extension.
	 * @param int $context 
	 * @return \MvcCore\Ext\Forms\Validators\Local
	 */
	public function SetContext ($context = \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM) {
		$this->context = $context;
		return $this;
	}
	
	/**
	 * Validation method.
	 * Check submitted value by validator specific rules and 
	 * if there is any error, call: `$this->field->AddValidationError($errorMsg, $errorMsgArgs, $replacingCallable);` 
	 * with not translated error message. Return safe submitted value as result or `NULL` if there 
	 * is not possible to return safe valid value.
	 * @param string|array			$rawSubmittedValue	Raw submitted value, string or array of strings.
	 * @return string|array|NULL	Safe submitted value or `NULL` if not possible to return safe value.
	 */
	public function Validate ($rawSubmittedValue) {
		$this->completeReflectionObjects();

		$debugClass = $this->form->GetController()->GetApplication()->GetDebugClass();
		$errorMsg = static::GetErrorMessage(static::ERROR_LOCAL_VALIDATION);
		$safeValue = NULL;
		try {

			$safeValue = $this->reflectionMethod->invokeArgs(
				$this->reflectionInvokeObject, [$rawSubmittedValue, $this->field]
			);

		} catch (\Exception $e) { // backward compatibility
			$debugClass::Log($e);
			$this->field->AddValidationError($errorMsg);
		} catch (\Throwable $e) {
			$debugClass::Log($e);
			$this->field->AddValidationError($errorMsg);
		}

		return $safeValue;
	}

	/**
	 * Initialize reflection invoke method and object.
	 * @throws \InvalidArgumentException 
	 * @return void
	 */
	protected function completeReflectionObjects () {
		if ($this->reflectionMethod !== NULL) return;

		if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM) != 0) {
			$this->reflectionInvokeObject = $this->form;
			$this->reflectionMethod = new \ReflectionMethod($this->reflectionInvokeObject, $this->method);

		} else if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_FORM_STATIC) != 0) {
			$this->reflectionMethod = new \ReflectionMethod(get_class($this->form), $this->method);

		} else if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL) != 0) {
			$this->reflectionInvokeObject = $this->form->GetController();
			$this->reflectionMethod = new \ReflectionMethod($this->reflectionInvokeObject, $this->method);

		} else if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_CTRL_STATIC) != 0) {
			$this->reflectionMethod = new \ReflectionMethod(get_class($this->form->GetController()), $this->method);

		} else {
			$modelFormInterface = 'MvcCore\\Ext\\ModelForms\\IForm';
			if (!interface_exists($modelFormInterface)) throw new \InvalidArgumentException(
				"For model context validation, you have to install extension `mvccore/ext-model-form`."
			);
			$toolClass = $this->form->GetController()->GetApplication()->GetToolClass();
			$formImplementsInterface = $toolClass::CheckClassInterface(
				get_class($this->form), $modelFormInterface, TRUE, FALSE
			);
			if (!$formImplementsInterface) throw new \InvalidArgumentException(
				"For model context validation, you have to implement form interface `\\{$modelFormInterface}`."
			);
			/** @var $modelForm \MvcCore\Ext\ModelForms\Form */
			$modelForm = $this->form;
			if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL) != 0) {
				$this->reflectionInvokeObject = $modelForm->GetModelInstance();
				$this->reflectionMethod = new \ReflectionMethod($this->reflectionInvokeObject, $this->method);
				
			} else if (($this->context & \MvcCore\Ext\Forms\IField::VALIDATOR_CONTEXT_MODEL_STATIC) != 0) {
				$this->reflectionMethod = new \ReflectionMethod($modelForm->GetModelClass(), $this->method);

			} else {
				throw new \InvalidArgumentException(
					"Unknown local validator context flag: `{$this->context}` for method `{$this->method}`."
				);
			}
		}
		
		$this->reflectionMethod->setAccessible(TRUE);
	}
}