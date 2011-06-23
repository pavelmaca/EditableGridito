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
	 * @throws \Nette\InvalidArgumentException When entity handler is not callable.
	 */
	public function setEntityInsertHandler($entityInsertHandler) {
		if (!is_callable($entityInsertHandler)) {
			throw new \Nette\InvalidArgumentException("\$entityInsertHandler is not callable.");
		}
		$this->entityInsertHandler = $entityInsertHandler;
	}

	/**
	 * @return callback
	 * @throws \Nette\InvalidStateException when self::$entityInsertHandler not set
	 */
	public function getEntityInsertHandler() {
		if (!isset($this->entityInsertHandler)) {
			throw new \Nette\InvalidStateException( __CLASS__ . "::\$entityInsertHandler is not set.");
		}
		return $this->entityInsertHandler;
	}

	/**
	 * @param callback $entityUpdateHandler
	 * @throws \Nette\InvalidArgumentException When entity handler is not callable.
	 */
	public function setEntityUpdateHandler($entityUpdateHandler) {
		if (!is_callable($entityUpdateHandler)) {
			throw new \Nette\InvalidArgumentException("\$entityUpdateHandler is not callable.");
		}
		$this->entityUpdateHandler = $entityUpdateHandler;
	}

	/**
	 * @return callback
	 * @throws \Nette\InvalidStateException when self::$entityUpdateHandler not set
	 */
	public function getEntityUpdateHandler() {
		if (!isset($this->entityUpdateHandler)) {
			throw new \Nette\InvalidStateException(__CLASS__ . "::\$entityUpdateHandler is not set.");
		}
		return $this->entityUpdateHandler;
	}
	
	public function getEntityManager(){
		return $this->qb->getEntityManager();
	}

	/**
	 * @param string|int $id
	 * @return mixed
	 * @throws \Nette\InvalidStateException when entity not found or duplicity exists
	 */
	private function entityFind($id) {
		try {
			return $this->qb->where($this->qb->getRootAlias() . "." . $this->getPrimaryKey() . " = :id")
				->setParameter("id", $id)
				->getQuery()
				->getSingleResult();
		} catch (\Doctrine\ORM\NoResultException $e) {
			throw new \Nette\InvalidStateException("Entity with id: '$id' not found");
		} catch (\Doctrine\ORM\NonUniqueResultException $e) {
			throw new \Nette\InvalidStateException("Entity with id: '$id' isn't unique!");
		}
	}
	
	
	/** interface IEditableModel */
	
	/**
	 * @param string|int $id
	 * @return array 
	 * @throws \Nette\UnexpectedValueException
	 * @todo do it better
	 */
	public function findRow($id) {
		$data = array();
		foreach ((array) $this->entityFind($id) as $key => $value) {
			if (preg_match("~\\x00(?P<key>[^\\x00]+)$~", $key, $found)) { //ignore namespace in key
				$data[$found["key"]] = $value;
			}
			else
				throw new \Nette\UnexpectedValueException("Unexpected structure of \$key : '$key'");
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
	 * @throws \Nette\InvalidStateException When updating row not found.
	 */
	public function updateRow($id, $rawValues) {
		$entity = $this->entityFind($id);
		if(!$entity){
			throw new \Nette\InvalidStateException("Can not find row with id:'$id'");
		}
		
		$this->qb->getEntityManager()->persist($entity);

		$handler = $this->getEntityUpdateHandler();
		$handler($entity, $rawValues);

		$this->qb->getEntityManager()->flush();
	}
}