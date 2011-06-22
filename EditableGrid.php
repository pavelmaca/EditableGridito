<?php

namespace Gridito;

use Nette\Application\UI\Form as AppForm;

/**
 * @author Pavel Máca
 * @license MIT
 * Editable Grid with ADD, EDIT and REMOVE actions.
 */
class EditableGrid extends Grid {
	
	const MESSAGE_ADD = "::add::";
	const MESSAGE_EDIT = "::edit::";	

	/** @var type IEditableModel */
	protected $model;
	
	/** @var string Class used as default when creting edit/add form */
	private $editControlClass = "\Nette\Forms\TextInput";
	
	private $formFilter = array(__CLASS__, '_bracket');
	
	private $defaultValueFilter = array(__CLASS__, '_bracket');
	
	/** @var string FlashMessage show after row created */
	private static $insertedMessage = "Row was created successfully.";
	
	/** @var string FlashMessage show after row updated */
	private static $editedMessage = "Row updated.";
	
	/** @var string FlashMessage show after row removed */
	private static $removedMessage = "Row was removed successfully.";

	static function _bracket($v) {
		return $v;
	}

	/**
	 * @param \Nette\IComponentContainer $parent
	 * @param string $name 
	 */
	public function __construct(\Nette\ComponentModel\IContainer $parent = null, $name = null) {
		parent::__construct($parent, $name);

		static $extended = false;
		if (!$extended) {
			Column::extensionMethod("setEditable", callback($this, "setEditable"));
			Column::extensionMethod("getEditable", callback($this, "getEditable"));
			Column::extensionMethod("setEditableText", callback($this, "setEditableText"));
			Column::extensionMethod("setEditableTextArea", callback($this, "setEditableTextArea"));
			Column::extensionMethod("setEditableSelect", callback($this, "setEditableSelect"));
			Column::extensionMethod("setEditableRadioList", callback($this, "setEditableRadioList"));
			Column::extensionMethod("setEditableCheckbox", callback($this, "setEditableCheckbox"));
			
			
			Column::extensionMethod("setAddable", callback($this, "setAddable"));
			Column::extensionMethod("getAddable", callback($this, "getAddable"));
			Column::extensionMethod("setAddableText", callback($this, "setAddableText"));
			Column::extensionMethod("setAddableTextArea", callback($this, "setAddableTextArea"));
			Column::extensionMethod("setAddableSelect", callback($this, "setAddableSelect"));
			Column::extensionMethod("setAddableRadioList", callback($this, "setAddableRadioList"));
			Column::extensionMethod("setAddableCheckbox", callback($this, "setAddableCheckbox"));
			$extended = true;
		}
	}

	/*	 * *************** Messages ****** */

	/**
	 * @param string|NULL $message
	 * @return EditableGrid 
	 */
	public function setInsertedMessage($message) {
		self::$insertedMessage = $message;
		return $this;
	}

	/**
	 * @param string|NULL $message
	 * @return EditableGrid 
	 */
	public function setEditedMessage($message) {
		self::$editedMessage = $message;
		return $this;
	}

	/**
	 * @param string|NULL $message
	 * @return EditableGrid 
	 */
	public function setRemovedMessage($message) {
		self::$removedMessage = $message;
		return $this;
	}

	/** @return string */
	public function getInsertedMessage() {
		return self::$insertedMessage;
	}

	/** @return string */
	public function getEditedMessage() {
		return self::$editedMessage;
	}

	/** @return string */
	public function getRemovedMessage() {
		return self::$removedMessage;
	}

	/*	 * *************** Filters ****** */

	/**
	 * @param callback $filter
	 * @return EditableGrid 
	 */
	public function setDefaultValueFilter($filter) {
		$this->defaultValueFilter = $filter;
		return $this;
	}

	/**
	 * @param callback $filter
	 * @return EditableGrid 
	 */
	public function setFormFilter($filter) {
		$this->formFilter = $filter;
		return $this;
	}

	/*	 * *************** Model ****** */

	/**
	 * @param IModel $model
	 * @return EditableGrid
	 */
	public function setModel(IModel $model) {
		if (!$model instanceof IEditableModel) {
			throw new \InvalidArgumentException("Model must implements \Gridito\IEditableModel");
		}
		return parent::setModel($model);
	}

	/*	 * *************** Buttons ****** */

	/**
	 * @param string $name
	 * @param string|NULL $label
	 * @param array $options
	 * @return WindowButton
	 * @throws \InvalidArgumentException
	 */
	public function addAddButton($name, $label = null, array $options = array()) {
		if (isset($options["handler"])) {
			throw new \InvalidArgumentException(__CLASS__ . ":" . __METHOD__ . " \$options['handler'] is reserved.");
		}
	
		$grid = $this;
		$options["handler"] = function () use ($grid) {
				$grid["addForm"]->render();
			};

		return $this->addToolbarWindowButton($name, $label, $options);
	}

	/**
	 * @param string $name
	 * @param string|NULL $label
	 * @param array $options
	 * @return WindowButton
	 * @throws \InvalidArgumentException
	 */
	public function addEditButton($name, $label = null, array $options = array()) {
		if (isset($options["handler"])) {
			throw new \InvalidArgumentException(__CLASS__ . ":" . __METHOD__ . " \$options['handler'] is reserved.");
		}

		$grid = $this;
		$model = $this->model;
		$filter = $this->defaultValueFilter;
		$options["handler"] = function ($id) use ($grid, $model, $filter) {
				$grid["editForm"]->setDefaults(call_user_func($filter, $model->findRow($id)));
				$grid["editForm"]->render();
			};

		if(isset($options["editedMessage"])){
			$this->setEditedMessage($options["editedMessage"]);
		}
		//remove editable-only options
		unset($options["editedMessage"]);

		return $this->addWindowButton($name, $label, $options);
	}

	/**
	 * @param string $name
	 * @param string|NULL $label
	 * @param array $options
	 * @return Button
	 * @throws \InvalidArgumentException
	 */
	public function addRemoveButton($name, $label = null, array $options = array()) {
		if (isset($options["handler"])) {
			throw new \InvalidArgumentException(__CLASS__ . ":" . __METHOD__ . " \$options['handler'] is reserved.");
		}

		$grid = $this;
		$model = $this->model;
		$options["handler"] = function ($id) use ($grid, $model) {
				$model->removeRow($id);
				$grid->flashMessage($grid->getRemovedMessage());
			};

		return $this->addButton($name, $label, $options);
	}

	/*	 * *************** Handlers ****** */

	/**
	 * @param string $message
	 * @param bool $insert
	 * @return \Closure 
	 */
	private function createSubmitHandler($messageFlag, $insert = false) {
		$grid = $this;
		$model = $this->model;
		$filter = $grid->formFilter;
		return function ($form) use ($grid, $model, $filter, $messageFlag, $insert) {
			$vals = $form->values;

			$rawData = call_user_func($filter, $form->values, $form);
			
			if ($insert === true) {
				$model->addRow($rawData);
			} else {
				unset($rawData[$grid->getModel()->getPrimaryKey()]);
				$model->updateRow($vals[$grid->getModel()->getPrimaryKey()], $rawData);
			}

			switch($messageFlag){
				case EditableGrid::MESSAGE_ADD: $message = $grid->getInsertedMessage();
					break;
				case EditableGrid::MESSAGE_EDIT: $message = $grid->getEditedMessage();
					break;
				default: throw new \InvalidArgumentException("Invalid message flag.");
			}
			if($message !== NULL){
				$grid->flashMessage($message);
			}
			
			$grid->redirect("this");
		};
	}

	/*
	  protected function createComponentAddForm($name){
	  $this->createBaseForm($name);

	  $this->editableForm->onSubmit[] = $this->createSubmitHandler(true, $this->insertedMessage);
	  } */

	/*	 * *************** Factory ****** */

	/**
	 * @param string $name 
	 */
	protected function createComponentEditForm($name) {
		$form = new AppForm($this, $name);
		$form->addProtection();

		$form->addHidden($this->getModel()->getPrimaryKey());

		$form->onSubmit[] = $this->createSubmitHandler(self::MESSAGE_EDIT);
	}
	
	/**
	 * @param string $name 
	 */
	protected function createComponentAddForm($name) {
		$form = new AppForm($this, $name);
		$form->addProtection();
		
		$form->onSubmit[] = $this->createSubmitHandler(self::MESSAGE_ADD, true);
	}
	
	/*	 * *************** Aliases ****** */

	/**
	 * @return AppForm 
	 */
	public function getEditableForm() {
		return $this["editForm"];
	}
	
	/**
	 * @return AppForm 
	 */
	public function getAddableForm() {
		return $this["addForm"];
	}

	/** extending methods */

	/**
	 * @param Column $column
	 * @param bool $enable
	 * @return \Nette\Forms\TextInput|NULL
	 */
	public function setEditable(Column $column, $enable = true) {
		if($enable === false){
			unset($this->getEditableForm()->{$column->getName()});
			return;
		}
		
		return  $this->setEditableText($column);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param int $cols
	 * @param int $maxLength
	 * @return \Nette\Forms\TextInput
	 */
	public function setEditableText(Column $column, $label = NULL, $cols = NULL, $maxLength = NULL) {
		return $this->getEditableForm()->addText($column->getName(), ($label ?: $column->getLabel()), $cols, $maxLength);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param array $items
	 * @return \Nette\Forms\SelectBox
	 */
	public function setEditableSelect(Column $column, $label = NULL, array $items = NULL) {
		return $this->getEditableForm()->addSelect($column->getName(), ($label ?: $column->getLabel()), $items);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $caption
	 * @return \Nette\Forms\Checkbox
	 */
	public function setEditableCheckbox(Column $column, $caption = NULL) {
		return $this->getEditableForm()->addCheckbox($column->getName(), ($caption ?: $column->getLabel()));
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param array $items
	 * @return \Nette\Forms\RadioList
	 */
	public function setEditableRadioList(Column $column, $label = NULL, array $items = NULL) {
		return $this->getEditableForm()->addRadioList($column->getName(), ($label ?: $column->getLabel()), $items);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param int $cols
	 * @param int $rows
	 * @return \Nette\Forms\TextArea
	 */
	public function setEditableTextArea(Column $column, $label = NULL,  $cols = 40, $rows = 10) {
		return $this->getEditableForm()->addTextArea($column->getName(), ($label ?: $column->getLabel()), $cols, $rows);
	}
	
	/**
	 * @param Column $column
	 * @param bool $enable
	 * @return \Nette\Forms\TextInput|NULL
	 */
	public function setAddable(Column $column, $enable = true) {
		if($enable === false){
			unset($this->getEditableForm()->{$column->getName()});
			return;
		}
		
		return  $this->setAddableText($column);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param int $cols
	 * @param int $maxLength
	 * @return \Nette\Forms\TextInput
	 */
	public function setAddableText(Column $column, $label = NULL, $cols = NULL, $maxLength = NULL) {
		return $this->getAddableForm()->addText($column->getName(), ($label ?: $column->getLabel()), $cols, $maxLength);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param array $items
	 * @return \Nette\Forms\SelectBox
	 */
	public function setAddableSelect(Column $column, $label = NULL, array $items = NULL) {
		return $this->getAddableForm()->addSelect($column->getName(), ($label ?: $column->getLabel()), $items);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $caption
	 * @return \Nette\Forms\Checkbox
	 */
	public function setAddableCheckbox(Column $column, $caption = NULL) {
		return $this->getAddableForm()->addCheckbox($column->getName(), ($caption ?: $column->getLabel()));
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param array $items
	 * @return \Nette\Forms\RadioList
	 */
	public function setAddableRadioList(Column $column, $label = NULL, array $items = NULL) {
		return $this->getAddableForm()->addRadioList($column->getName(), ($label ?: $column->getLabel()), $items);
	}
	
	/**
	 * @param Column $column
	 * @param string|NULL $label
	 * @param int $cols
	 * @param int $rows
	 * @return \Nette\Forms\TextArea
	 */
	public function setAddableTextArea(Column $column, $label = NULL,  $cols = 40, $rows = 10) {
		return $this->getAddableForm()->addTextArea($column->getName(), ($label ?: $column->getLabel()), $cols, $rows);
	}

	/**
	 * @param Column $column
	 * @return \Nette\Forms\IFormControl 
	 */
	public function getEditable(Column $column) {
		return $this->getEditableForm()->getComponent($column->getName());
	}
	
	/**
	 *
	 * @param Column $column
	 * @return \Nette\Forms\IFormControl 
	 */
	public function getAddable(Column $column) {
		return $this->getEditableForm()->getComponent($column->getName());
	}

}