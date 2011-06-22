<?php

namespace Gridito;

/**
 * Doctrine Editable QueryBuilder model
 *
 * @author Pavel Máca
 * @license MIT
 */
class DoctrineEditableQueryBuilderModel extends DoctrineQueryBuilderModel implements IEditableModel
{
	/** @var callback */
	protected $entityInsertHandler;
	
	/** @var callback */
	protected $entityUpdateHandler;

	/**
	 * @param callback $entityInsertHandler 
	 */
	public function setEntityInsertHandler($entityInsertHandler) {
		if (!is_callable($entityInsertHandler)) {
			throw new \InvalidArgumentException("\$entityInsertHandler is not callable.");
		}
		$this->entityInsertHandler = $entityInsertHandler;
	}

	/**
	 * @return callback
	 * @throws \InvalidStateException when self::$entityInsertHandler not set
	 */
	public function getEntityInsertHandler() {
		if (!isset($this->entityInsertHandler)) {
			throw new \InvalidStateException( __CLASS__ . "::\$entityInsertHandler is not set.");
		}
		return $this->entityInsertHandler;
	}

	/**
	 * @param callback $entityUpdateHandler
	 */
	public function setEntityUpdateHandler($entityUpdateHandler) {
		if (!is_callable($entityUpdateHandler)) {
			throw new \InvalidArgumentException("\$entityUpdateHandler is not callable.");
		}
		$this->entityUpdateHandler = $entityUpdateHandler;
	}

	/**
	 * @return callback
	 * @throws \InvalidStateException when self::$entityUpdateHandler not set
	 */
	public function getEntityUpdateHandler() {
		if (!isset($this->entityUpdateHandler)) {
			throw new \InvalidStateException(__CLASS__ . "::\$entityUpdateHandler is not set.");
		}
		return $this->entityUpdateHandler;
	}
	
	public function getEntityManager(){
		return $this->qb->getEntityManager();
	}

	/**
	 * @param string|int $id
	 * @return mixed
	 * @throws \InvalidStateException when entity not found or duplicity exists
	 */
	private function entityFind($id) {
		try {
			return $this->qb->where($this->qb->getRootAlias() . "." . $this->getPrimaryKey() . " = :id")
				->setParameter("id", $id)
				->getQuery()
				->getSingleResult();
		} catch (\Doctrine\ORM\NoResultException $e) {
			throw new \InvalidStateException("Entity with id: '$id' not found");
		} catch (\Doctrine\ORM\NonUniqueResultException $e) {
			throw new \InvalidStateException("Entity with id: '$id' isn't unique!");
		}
	}
	
	
	/** interface IEditableModel */
	
	/**
	 * @param string|int $id
	 * @return array 
	 * @todo do it better
	 */
	public function findRow($id) {
		$data = array();
		foreach ((array) $this->entityFind($id) as $key => $value) {
			if (preg_match("~\\x00(?P<key>[^\\x00]+)$~", $key, $found)) {
				$data[$found["key"]] = $value;
			}
			else
				throw new \UnexpectedValueException("Unexpected structure of \$key : '$key'");
		}
		return $data;
	}

	/**
	 * @param string|int $id 
	 */
	public function removeRow($id) {
		$entity = $this->entityFind($id);
		
		$this->qb->getEntityManager()->remove($entity);
		$this->qb->getEntityManager()->flush();
	}

	/**
	 * @param array $rawValues
	 */
	public function addRow($rawValues) {
		$handler = $this->getEntityInsertHandler();
		$entity = $handler($rawValues);
		
		$this->qb->getEntityManager()->persist($entity);
		$this->qb->getEntityManager()->flush();
	}

	/**
	 * @param string|int $id
	 * @param array $rawValues 
	 */
	public function updateRow($id, $rawValues) {
		$entity = $this->entityFind($id);
		if(!$entity){
			throw new \InvalidStateException("Can not find row with id:'$id'");
		}
		
		$this->qb->getEntityManager()->persist($entity);

		$handler = $this->getEntityUpdateHandler();
		$handler($entity, $rawValues);

		$this->qb->getEntityManager()->flush();
	}
}