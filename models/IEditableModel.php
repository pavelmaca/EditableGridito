<?php

namespace Gridito;

/**
 * Editable data model
 *
 * @author Pavel Máca
 * @license MIT
 */
interface IEditableModel extends IModel {
	
	public function findRow($id);
	
	public function addRow($rawValues);
	
	public function updateRow($id, $rawValues);
	
	public function removeRow($id);
}
