<?php
/**
 * CDbTransaction class file
 * @author andehuang
 */

/**
 * CDbTransaction represents a DB transaction.
 *
 * It is usually created by calling {@link CDbConnection::beginTransaction}.
 *
 * The following code is a common scenario of using transactions:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollBack();
 * }
 * </pre>
 *
 * @property CDbConnection $connection The DB connection for this transaction.
 * @property boolean $active Whether this transaction is active.
 */
class CDbTransaction extends CComponent
{
	private $_connection=null;
	private $_active;
	public $connectionRetryCount;		//执行事务，是不允许重试的。

	/**
	 * Constructor.
	 * @param CDbConnection $connection the connection associated with this transaction
	 * @see CDbConnection::beginTransaction
	 */
	public function __construct(CDbConnection $connection)
	{
		$this->_connection=$connection;
		$this->_active=true;
		
		$this->connectionRetryCount = $connection->retryCount;
		$connection->retryCount = 0;
	}

	/**
	 * Commits a transaction.
	 * @throws CException if the transaction or the DB connection is not active.
	 */
	public function commit()
	{
		if($this->_active)
		{
			Mod::trace('Committing transaction','system.db.CDbTransaction');
		    	//$connection->retryCount = $this->connectionRetryCount;
			$this->_connection->getPdoInstance('master')->commit();
			$this->_connection->updateMasterTime();
			$this->_active=false;
		}
		else
			throw new CDbException(Mod::t('mod','CDbTransaction is inactive and cannot perform commit or roll back operations.'));
	}

	/**
	 * Rolls back a transaction.
	 * @throws CException if the transaction or the DB connection is not active.
	 */
	public function rollback()
	{
		if($this->_active)
		{
			Mod::trace('Rolling back transaction','system.db.CDbTransaction');
			//$connection->retryCount = $this->connectionRetryCount;
			$this->_connection->getPdoInstance('master')->rollBack();
			$this->_connection->updateMasterTime();
			$this->_active=false;
		}
		else
			throw new CDbException(Mod::t('mod','CDbTransaction is inactive and cannot perform commit or roll back operations.'));
	}

	/**
	 * @return CDbConnection the DB connection for this transaction
	 */
	public function getConnection()
	{
		return $this->_connection;
	}

	/**
	 * @return boolean whether this transaction is active
	 */
	public function getActive()
	{
		return $this->_active;
	}

	/**
	 * @param boolean $value whether this transaction is active
	 */
	protected function setActive($value)
	{
		$this->_active=$value;
		
		if($value)
		{
			$this->connectionRetryCount = $connection->retryCount;
			$connection->retryCount = 0;
		}
		else
			$connection->retryCount = $this->connectionRetryCount;
	}
}
