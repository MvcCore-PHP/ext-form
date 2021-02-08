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

namespace MvcCore\Ext\Forms;

/**
 * Responsibility: Base validator class with base methods implementations.
 *				   This class is not possible to instantiate, you need to extend 
 *				   this class and define custom validation rules.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
abstract class Validator implements \MvcCore\Ext\Forms\IValidator {

	/**
	 * Form instance where was validator created.
	 * Every validator instance belongs to only one form instance.
	 * @var \MvcCore\Ext\Form
	 */
	protected $form = NULL;

	/**
	 * Currently validated form field instance.
	 * Before every `Validate()` method call, there is called
	 * `$validator->SetField($field);` to work with proper field 
	 * instance during validation.
	 * @var \MvcCore\Ext\Forms\Field
	 */
	protected $field = NULL;

	/**
	 * Validator custom error message strings (not translated) 
	 * with replacements for field names and more specific info 
	 * to tell the user what happened or what to do more.
	 * @var \string[]
	 */
	protected static $errorMessages = [];

	/**
	 * An associative array for extended classes, where you could define in 
	 * camel case field specific properties to get before validation into local
	 * context with values from field instance, given in method local `SetField()`.
	 * All keys defined in this array are field names to transfer from field 
	 * instance into local context. Values in this array is good practise to 
	 * define with `NULL`, but values also could be used as default values, if 
	 * there is `NULL` value for defined property in field instance. Then value
	 * from this array is used in local context and is also set into field by 
	 * field setter method.
	 * @var array
	 */
	protected static $fieldSpecificProperties = [];

	/**
	 * Remembered value from `\MvcCore\Application::GetInstance()->GetToolClass();`
	 * @var string
	 */
	protected static $toolClass = '';

	/**
	 * Create every time new validator instance with configured form instance. No singleton.
	 * @param array $constructorConfig	Configuration arguments for constructor, 
	 *									validator's constructor first param.
	 * @return \MvcCore\Ext\Forms\Validator
	 */
	public static function CreateInstance (array $constructorConfig = []) {
		$validator = new static($constructorConfig);
		$validator::$toolClass = \MvcCore\Application::GetInstance()->GetToolClass();
		return $validator;
	}

	/**
	 * Create new form field validator instance.
	 * @param array $cfg Config array with protected properties and it's 
	 *					 values which you want to configure, presented 
	 *					 in camel case properties names syntax.
	 * @return void
	 */
	public function __construct (array $cfg = []) {
		foreach ($cfg as $propertyName => $propertyValue) {
			if ($propertyName == 'field' || $propertyName == 'form' || !property_exists($this, $propertyName)) {
				$this->throwNewInvalidArgumentException(
					'Property `'.$propertyName.'` is not possible '
					.'to configure by constructor `$cfg` param.'
				);
			} else {
				$this->{$propertyName} = $propertyValue;
			}
		}
	}

	/**
	 * Set up form instance, where is validator created during submit.
	 * @param \MvcCore\Ext\Form $form 
	 * @return \MvcCore\Ext\Forms\Validator
	 */
	public function SetForm (\MvcCore\Ext\IForm $form) {
		$this->form = $form;
		return $this;
	}

	/**
	 * Set up field instance, where is validated value by this 
	 * validator during submit before every `Validate()` method call.
	 * This method is also called once, when validator instance is separately 
	 * added into already created field instance to process any field checking.
	 * @param \MvcCore\Ext\Forms\Field $field 
	 * @return \MvcCore\Ext\Forms\Validator
	 */
	public function SetField (\MvcCore\Ext\Forms\IField $field) {
		$this->field = $field;
		if (static::$fieldSpecificProperties) 
			$this->setUpFieldProps(static::$fieldSpecificProperties);
		return $this;
	}
	
	/**
	 * Set predefined validator custom error message strings (not translated) 
	 * with replacements for field names and more specific info 
	 * to tell the user what happened or what to do more.
	 * @param array $errorMessages Indexes are integers and values are error message strings.
	 * @return array
	 */
	public static function SetErrorMessages ($errorMessages) {
		return static::$errorMessages = $errorMessages;
	}
	
	/**
	 * Return predefined validator custom error message strings (not translated) 
	 * with replacements for field names and more specific info 
	 * to tell the user what happened or what to do more.
	 * @param int $errorMsgIndex Integer index for `static::$errorMessages` array.
	 * @return string
	 */
	public static function GetErrorMessage ($errorMsgIndex) {
		return static::$errorMessages[$errorMsgIndex];
	}

	/**
	 * Validation method.
	 * Check submitted value by validator specific rules and 
	 * if there is any error, call: `$this->field->AddValidationError($errorMsg, $errorMsgArgs, $replacingCallable);` 
	 * with not translated error message. Return safe submitted value as result or `NULL` if there 
	 * is not possible to return safe valid value.
	 * @param string|array			$submitValue	Raw submitted value, string or array of strings.
	 * @return string|array|NULL	Safe submitted value or `NULL` if not possible to return safe value.
	 */
	public abstract function Validate ($rawSubmittedValue);

	/**
	 * Throw new `\InvalidArgumentException` with given
	 * error message and append automatically current class, 
	 * field name, form id, field class name and form class name.
	 * @param string $errorMsg 
	 * @throws \InvalidArgumentException 
	 */
	protected function throwNewInvalidArgumentException ($errorMsg) {
		$msgs = [];
		if ($this->field) 
			$msgs[] = 'Field name: `'.$this->field->GetName() . '`, Field type: `'.get_class($this->field).'`';
		if ($this->form) 
			$msgs[] = 'Form id: `'.$this->form->GetId() . '`, Form type: `'.get_class($this->form).'`';
		throw new \InvalidArgumentException(
			'['.get_class().'] ' . $errorMsg . ($msgs ? ' '.implode(', ', $msgs) : '')
		);
	}

	/**
	 * Set up field specific properties.
	 * If field returns it's specific value (`array_key_exists()`), use it for 
	 * validator. If field doesn't return any specific value and validator has 
	 * this specific value defined, try to set up this specific value to field.
	 * If there is no specific value in field and not even in validator, set into
	 * validator default value.
	 * @param array $fieldPropsDefaultValidValues Array with key as property 
	 *											  name and value as default 
	 *											  validator value, if there is 
	 *											  nothing in field and nothing 
	 *											  even in validator itself.
	 * @return void
	 */
	protected function setUpFieldProps ($fieldPropsDefaultValidValues = []) {
		$fieldValues = $this->field->GetValidatorData($fieldPropsDefaultValidValues);
		foreach ($fieldPropsDefaultValidValues as $propName => $defaultValidatorValue) {
			$fieldValue = array_key_exists($propName, $fieldValues)
				? $fieldValues[$propName]
				: NULL;
			if ($fieldValue !== NULL /*&& $this->{$propName} === NULL*/) {
				$this->{$propName} = $fieldValue;
			} else {
				$this->{$propName} = $defaultValidatorValue;
			}
		}
	}
}
