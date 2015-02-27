<?php
/**
 * CDbConnection class file
 * @author andehuang
 * add insert update delete
 * add sql realtion:group limit and so on
 */

class CDbCommand extends CComponent
{
	/**
	 * @var array the parameters (name=>value) to be bound to the current query.
	 * @since 1.0
	 */
	public $params=array();

	private $_connection;
	private $_text;
	private $_paramLog=array();
	private $_statement;
	private $_query;
	private $_fetchMode = array(PDO::FETCH_ASSOC);

	/**
	 * Constructor.
	 * @param CDbConnection $connection the database connection
	 * @param string $query the DB query to be executed
	 */
	public function __construct(CDbConnection $connection,$query=null)
	{
		$this->_connection=$connection;
		$this->setText($query);
	}

	/**
	 * Set the statement to null when serializing.
	 * @return array
	 */
	public function __sleep()
	{
		$this->_statement=null;
		return array_keys(get_object_vars($this));
	}

	/**
	 * Set the default fetch mode for this statement
	 * @param mixed $mode fetch mode
	 * @return CDbCommand
	 * @see http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
	 * @since 1.0
	 */
	public function setFetchMode($mode)
	{
		$params=func_get_args();
		$this->_fetchMode = $params;
		return $this;
	}

	/**
	 * Cleans up the command and prepares for building a new query.
	 * This method is mainly used when a command object is being reused
	 * multiple times for building different queries.
	 * Calling this method will clean up all internal states of the command object.
	 * @return CDbCommand this command instance
	 * @since 1.0
	 */
	public function reset()
	{
		$this->_text=null;
		$this->_statement=null;
		$this->params=array();
		return $this;
	}

	/**
	 * @return string the SQL statement to be executed
	 */
	public function getText()
	{
		if($this->_text=='' && !empty($this->_query))
			$this->setText($this->buildQuery($this->_query));
		return $this->_text;
	}

	/**
	 * Specifies the SQL statement to be executed.
	 * Any previous execution will be terminated or cancel.
	 * @param string $value the SQL statement to be executed
	 * @return CDbCommand this command instance
	 */
	public function setText($value)
	{
		if($this->_connection->tablePrefix!==null && $value!='')
			$this->_text=preg_replace('/{{(.*?)}}/',$this->_connection->tablePrefix.'\1',$value);
		else
			$this->_text=$value;
		$this->cancel();
		return $this;
	}

	/**
	 * @return CDbConnection the connection associated with this command
	 */
	public function getConnection()
	{
		return $this->_connection;
	}

	/**
	 * @return PDOStatement the underlying PDOStatement for this command
	 * It could be null if the statement is not prepared yet.
	 */
	public function getPdoStatement()
	{
		return $this->_statement;
	}

	/**
	 * Cancels the execution of the SQL statement.
	 */
	public function cancel()
	{
		$this->_statement=null;
	}

	/**
	 * Binds a value to a parameter.
	 * @param mixed $name Parameter identifier. For a prepared statement
	 * using named placeholders, this will be a parameter name of
	 * the form :name. For a prepared statement using question mark
	 * placeholders, this will be the 1-indexed position of the parameter.
	 * @param mixed $value The value to bind to the parameter
	 * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
	 * @return CDbCommand the current command being executed
	 * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
	 */
	public function bindValue($name, $value)
	{
		$this->params[$name]=$value;
		return $this;
	}

	/**
	 * Binds a list of values to the corresponding parameters.
	 * This is similar to {@link bindValue} except that it binds multiple values.
	 * Note that the SQL data type of each value is determined by its PHP type.
	 * @param array $values the values to be bound. This must be given in terms of an associative
	 * array with array keys being the parameter names, and array values the corresponding parameter values.
	 * For example, <code>array(':name'=>'John', ':age'=>25)</code>.
	 * @return CDbCommand the current command being executed
	 * @since 1.0
	 */
	public function bindValues($values)
	{
		$this->params = array_merge($this->params,$values);
		return $this;
	}
	
	/**
	 * Creates and executes an INSERT SQL statement.
	 * The method will properly escape the column names, and bind the values to be inserted.
	 * @param string $table the table that new rows will be inserted into.
	 * @param array $columns the column data (name=>value) to be inserted into the table.
	 * @return integer number of rows affected by the execution.
	 * @since 1.1.6
	 */
	public function insert($table, $columns)
	{
		$params=array();
		$names=array();
		$placeholders=array();
		foreach($columns as $name=>$value)
		{
			$names[]=$this->_connection->quoteColumnName($name);
			if($value instanceof CDbExpression)
			{
				$placeholders[] = $value->expression;
				foreach($value->params as $n => $v)
					$params[$n] = $v;
			}
			else
			{
				$placeholders[] = ':' . $name;
				$params[':' . $name] = $value;
			}
		}
		$sql='INSERT INTO ' . $this->_connection->quoteTableName($table)
		. ' (' . implode(', ',$names) . ') VALUES ('
				. implode(', ', $placeholders) . ')';
		return $this->setText($sql)->execute($params);
	}
	
	/**
	 * Creates and executes an UPDATE SQL statement.
	 * The method will properly escape the column names and bind the values to be updated.
	 * @param string $table the table to be updated.
	 * @param array $columns the column data (name=>value) to be updated.
	 * @param mixed $conditions the conditions that will be put in the WHERE part. Please
	 * refer to {@link where} on how to specify conditions.
	 * @param array $params the parameters to be bound to the query.
	 * Do not use column names as parameter names here. They are reserved for <code>$columns</code> parameter.
	 * @return integer number of rows affected by the execution.
	 * @since 1.1.6
	 */
	public function update($table, $columns, $conditions='', $params=array())
	{
		$lines=array();
		foreach($columns as $name=>$value)
		{
			if($value instanceof CDbExpression)
			{
				$lines[]=$this->_connection->quoteColumnName($name) . '=' . $value->expression;
				foreach($value->params as $n => $v)
					$params[$n] = $v;
			}
			else
			{
				$lines[]=$this->_connection->quoteColumnName($name) . '=:' . $name;
				$params[':' . $name]=$value;
			}
		}
		$sql='UPDATE ' . $this->_connection->quoteTableName($table) . ' SET ' . implode(', ', $lines);
		if(($where=$this->processConditions($conditions))!='')
			$sql.=' WHERE '.$where;
		return $this->setText($sql)->execute($params);
	}
	
	/**
	 * Creates and executes a DELETE SQL statement.
	 * @param string $table the table where the data will be deleted from.
	 * @param mixed $conditions the conditions that will be put in the WHERE part. Please
	 * refer to {@link where} on how to specify conditions.
	 * @param array $params the parameters to be bound to the query.
	 * @return integer number of rows affected by the execution.
	 * @since 1.1.6
	 */
	public function delete($table, $conditions='', $params=array())
	{
		$sql='DELETE FROM ' . $this->_connection->quoteTableName($table);
		if(($where=$this->processConditions($conditions))!='')
			$sql.=' WHERE '.$where;
		return $this->setText($sql)->execute($params);
	}
	
	/**
	 * Sets the SELECT part of the query.
	 * @param mixed $columns the columns to be selected. Defaults to '*', meaning all columns.
	 * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. array('id', 'name')).
	 * Columns can contain table prefixes (e.g. "tbl_user.id") and/or column aliases (e.g. "tbl_user.id AS user_id").
	 * The method will automatically quote the column names unless a column contains some parenthesis
	 * (which means the column contains a DB expression).
	 * @param string $option additional option that should be appended to the 'SELECT' keyword. For example,
	 * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used. This parameter is supported since version 1.1.8.
	 * @return CDbCommand the command object itself
	 * @since 1.1.6
	 */
	public function select($columns='*', $option='')
	{
		if(is_string($columns) && strpos($columns,'(')!==false)
			$this->_query['select']=$columns;
		else
		{
			if(!is_array($columns))
				$columns=preg_split('/\s*,\s*/',trim($columns),-1,PREG_SPLIT_NO_EMPTY);
	
			foreach($columns as $i=>$column)
			{
				if(is_object($column))
					$columns[$i]=(string)$column;
				elseif(strpos($column,'(')===false)
				{
					if(preg_match('/^(.*?)(?i:\s+as\s+|\s+)(.*)$/',$column,$matches))
						$columns[$i]=$this->_connection->quoteColumnName($matches[1]).' AS '.$this->_connection->quoteColumnName($matches[2]);
					else
						$columns[$i]=$this->_connection->quoteColumnName($column);
				}
			}
			$this->_query['select']=implode(', ',$columns);
		}
		if($option!='')
			$this->_query['select']=$option.' '.$this->_query['select'];
		return $this;
	}
	
	/**
	 * Sets the FROM part of the query.
	 * @param mixed $tables the table(s) to be selected from. This can be either a string (e.g. 'tbl_user')
	 * or an array (e.g. array('tbl_user', 'tbl_profile')) specifying one or several table names.
	 * Table names can contain schema prefixes (e.g. 'public.tbl_user') and/or table aliases (e.g. 'tbl_user u').
	 * The method will automatically quote the table names unless it contains some parenthesis
	 * (which means the table is given as a sub-query or DB expression).
	 * @return CDbCommand the command object itself
	 * @since 1.1.6
	 */
	public function from($tables)
	{
		if(is_string($tables) && strpos($tables,'(')!==false)
			$this->_query['from']=$tables;
		else
		{
			if(!is_array($tables))
				$tables=preg_split('/\s*,\s*/',trim($tables),-1,PREG_SPLIT_NO_EMPTY);
			foreach($tables as $i=>$table)
			{
				if(strpos($table,'(')===false)
				{
					if(preg_match('/^(.*?)(?i:\s+as\s+|\s+)(.*)$/',$table,$matches))  // with alias
						$tables[$i]=$this->_connection->quoteTableName($matches[1]).' '.$this->_connection->quoteTableName($matches[2]);
					else
						$tables[$i]=$this->_connection->quoteTableName($table);
				}
			}
			$this->_query['from']=implode(', ',$tables);
		}
		return $this;
	}
	
	/*
	 *  @since 1.1.6
	 */
	public function where($conditions, $params=array())
	{
		$this->_query['where']=$this->processConditions($conditions);
	
		foreach($params as $name=>$value)
			$this->params[$name]=$value;
		return $this;
	}
	
	/**
	 * Generates the condition string that will be put in the WHERE part
	 * @param mixed $conditions the conditions that will be put in the WHERE part.
	 * @throws CDbException if unknown operator is used
	 * @return string the condition string to put in the WHERE part
	 */
	private function processConditions($conditions)
	{
		if(!is_array($conditions))
			return $conditions;
		elseif($conditions===array())
		return '';
		$n=count($conditions);
		$operator=strtoupper($conditions[0]);
		if($operator==='OR' || $operator==='AND')
		{
			$parts=array();
			for($i=1;$i<$n;++$i)
			{
				$condition=$this->processConditions($conditions[$i]);
				if($condition!=='')
					$parts[]='('.$condition.')';
			}
			return $parts===array() ? '' : implode(' '.$operator.' ', $parts);
		}
	
		if(!isset($conditions[1],$conditions[2]))
			return '';
	
		$column=$conditions[1];
		if(strpos($column,'(')===false)
			$column=$this->_connection->quoteColumnName($column);
	
		$values=$conditions[2];
		if(!is_array($values))
			$values=array($values);
	
		if($operator==='IN' || $operator==='NOT IN')
		{
			if($values===array())
				return $operator==='IN' ? '0=1' : '';
			foreach($values as $i=>$value)
			{
				if(is_string($value))
					$values[$i]=$this->_connection->quoteValue($value);
				else
					$values[$i]=(string)$value;
			}
			return $column.' '.$operator.' ('.implode(', ',$values).')';
		}
	
		if($operator==='LIKE' || $operator==='NOT LIKE' || $operator==='OR LIKE' || $operator==='OR NOT LIKE')
		{
			if($values===array())
				return $operator==='LIKE' || $operator==='OR LIKE' ? '0=1' : '';
	
			if($operator==='LIKE' || $operator==='NOT LIKE')
				$andor=' AND ';
			else
			{
				$andor=' OR ';
				$operator=$operator==='OR LIKE' ? 'LIKE' : 'NOT LIKE';
			}
			$expressions=array();
			foreach($values as $value)
				$expressions[]=$column.' '.$operator.' '.$this->_connection->quoteValue($value);
			return implode($andor,$expressions);
		}
	
		throw new CDbException(Mod::t('yii', 'Unknown operator "{operator}".', array('{operator}'=>$operator)));
	}

	/**
	 * Executes the SQL statement.
	 * This method is meant only for executing non-query SQL statement.
	 * No result set will be returned.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return integer number of rows affected by the execution.
	 * @throws CException execution failed
	 */
	public function execute($params=array())
	{
	    // perform report
        if ($this->_connection->enablePerformReport) {
			$report = new CPhpPerfReporter ();
			$cm = $this->__mapConnStr ( $this->_connection->connectionString );
			$report->beginPerfReport ( Mod::app ()->id, $cm ['host'], $cm ['port'], false, '', "" );
		}
        $params=array_merge($this->params,$params);
		
		if($this->_connection->enableParamLogging && $params!==array())
		{
			$p=array();
			foreach($params as $name=>$value)
				$p[$name]=$name.'='.var_export($value,true);
			$par='. Bound with ' .implode(', ',$p);
		}
		else
			$par='';
		Mod::trace('Executing SQL: '.$this->getText().$par,'system.db.CDbCommand');
		if($this->_connection->enableProfiling){
			Mod::beginProfile('system.db.CDbCommand.execute('.$this->getText().')','system.db.CDbCommand.execute');
		}
		for($retryCount=0; $retryCount<=$this->_connection->retryCount; ++$retryCount)
		{
			try
			{
				$__starttime = microtime(true);
				$statement=$this->_connection->getPdoInstance('master')->prepare($this->getText());
				if($params===array())
					$statement->execute();
				else
					$statement->execute($params);
				$n=$statement->rowCount();
				$this->_connection->updateMasterTime();
				$this->__nsfeedback($__starttime,"master");
				
				if ($this->_connection->enableProfiling) {
					Mod::endProfile ( 'system.db.CDbCommand.execute(' . $this->getText () . ')', 'system.db.CDbCommand.execute' );
				}
				if ($this->_connection->enablePerformReport) {
					$report->addParam ( 'skeys', $this->getText () );
					$report->endPerfReport ( 0 );
				}
				return $n;
			}
			catch(Exception $e)
			{
				//说明这个数据库链接坏了，关闭数据库链接，下次重新创建链接
				$this->_connection->close('master');
				
				$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
				$message = $e->getMessage();
				if(MOD_DEBUG)
					$message .= '. The SQL statement executed was: '.$this->getText().$par;
				Mod::log(Mod::t('mod','CDbCommand::execute() failed: {error}. The SQL statement executed was: {sql}.',array('{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.CDbCommand');
				
				//记录失败日志，需要区分是否为重试
				if($retryCount)
					Mod::log("sql execute fail(retryCount:$retryCount,sql:".$this->getText().$par.')', CLogger::LEVEL_ERROR, 'system.db.CDbCommand');
				else
					Mod::log("sql execute fail(sql:".$this->getText().$par.')', CLogger::LEVEL_ERROR, 'system.db.CDbCommand');
				
				//判断是否需要sleep
				if($this->_connection->retryCount && $this->_connection->retryInterval && $retryCount<$this->_connection->retryCount)
					usleep($this->_connection->retryInterval);
			}
		}
		//执行sql失败，抛异常。
		if($this->_connection->enableProfiling){
			Mod::endProfile('system.db.CDbCommand.execute('.$this->getText().')','system.db.CDbCommand.execute');
        }    
        if($this->_connection->enablePerformReport){
            $report->addParam('skeys',$this->getText());
            $report->endPerfReport(0);
        }
		throw new CDbException(Mod::t('mod','CDbCommand failed to execute the SQL statement: {error}',array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
	}

	/**
	 * Executes the SQL statement and returns query result.
	 * This method is for executing an SQL query that returns result set.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return CDbDataReader the reader object for fetching the query result
	 * @throws CException execution failed
	 */
	public function query($params=array())
	{
		return $this->queryInternal('',0,$params);
	}

	/**
	 * Executes the SQL statement and returns all rows.
	 * @param boolean $fetchAssociative whether each row should be returned as an associated array with
	 * column names as the keys or the array keys are column indexes (0-based).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return array all rows of the query result. Each array element is an array representing a row.
	 * An empty array is returned if the query results in nothing.
	 * @throws CException execution failed
	 */
	public function queryAll($fetchAssociative=true,$params=array())
	{
		return $this->queryInternal('fetchAll',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
	}

	/**
	 * Executes the SQL statement and returns the first row of the result.
	 * This is a convenient method of {@link query} when only the first row of data is needed.
	 * @param boolean $fetchAssociative whether the row should be returned as an associated array with
	 * column names as the keys or the array keys are column indexes (0-based).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return mixed the first row (in terms of an array) of the query result, false if no result.
	 * @throws CException execution failed
	 */
	public function queryRow($fetchAssociative=true,$params=array())
	{
		return $this->queryInternal('fetch',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
	}

	/**
	 * Executes the SQL statement and returns the value of the first column in the first row of data.
	 * This is a convenient method of {@link query} when only a single scalar
	 * value is needed (e.g. obtaining the count of the records).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return mixed the value of the first column in the first row of the query result. False is returned if there is no value.
	 * @throws CException execution failed
	 */
	public function queryScalar($params=array())
	{
		$result=$this->queryInternal('fetchColumn',0,$params);
		if(is_resource($result) && get_resource_type($result)==='stream')
			return stream_get_contents($result);
		else
			return $result;
	}

	/**
	 * Executes the SQL statement and returns the first column of the result.
	 * This is a convenient method of {@link query} when only the first column of data is needed.
	 * Note, the column returned will contain the first element in each row of result.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return array the first column of the query result. Empty array if no result.
	 * @throws CException execution failed
	 */
	public function queryColumn($params=array())
	{
		return $this->queryInternal('fetchAll',PDO::FETCH_COLUMN,$params);
	}/**
	 * Returns the ID of the last inserted row or sequence value
	 * @link http://www.php.net/manual/en/pdo.lastinsertid.php
	 * @param name string[optional] <p>
	 * Name of the sequence object from which the ID should be returned.
	 * </p>
	 * @return string If a sequence name was not specified for the name
	 * parameter, PDO::lastInsertId returns a
	 * string representing the row ID of the last row that was inserted into
	 * the database.
	 * </p>
	 * <p>
	 * If a sequence name was specified for the name
	 * parameter, PDO::lastInsertId returns a
	 * string representing the last value retrieved from the specified sequence
	 * object.
	 * </p>
	 * <p>
	 * If the PDO driver does not support this capability,
	 * PDO::lastInsertId triggers an
	 * IM001 SQLSTATE.
	 */
	public function getLastInsertId($name = null)
	{
		$statement=$this->_connection->getPdoInstance('master');
		
		return $statement->lastInsertId($name);
	}

	/**
	 * @param string $method method of PDOStatement to be called
	 * @param mixed $mode parameters to be passed to the method
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * binding methods and  the input parameters this way can improve the performance.
	 * @return mixed the method execution result
	 */
	private function queryInternal($method,$mode,$params=array())
	{
        // perform report
        if($this->_connection->enablePerformReport){
            $report = new CPhpPerfReporter();
            $cm = $this->__mapConnStr($this->_connection->connectionString);
            $report->beginPerfReport(Mod::app()->id, $cm['host'] , $cm['port'], false, '', "");  
        }
		$params=array_merge($this->params,$params);
		
		if($this->_connection->enableParamLogging && ($pars=array_merge($this->_paramLog,$params))!==array())
		{
			$p=array();
			foreach($pars as $name=>$value)
				$p[$name]=$name.'='.var_export($value,true);
			$par='. Bound with '.implode(', ',$p);
		}
		else
			$par='';
		Mod::trace('Querying SQL: '.$this->getText().$par,'system.db.CDbCommand');
		
		if($this->_connection->queryCachingCount>0 && $method!==''
				&& $this->_connection->queryCachingDuration>0
				&& $this->_connection->queryCacheID!==false
				&& ($cache=Mod::app()->getComponent($this->_connection->queryCacheID))!==null)
		{
			$this->_connection->queryCachingCount--;
			$cacheKey = $this->genDbCacheKey($params);
			if(($result=$cache->get($cacheKey))!==false)
			{
				Mod::trace('Query result found in cache','system.db.CDbCommand');
				return $result;
			}
		}
		
		if($this->_connection->enableProfiling){
			Mod::beginProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');
        }
		$dbIds = $this->_connection->getDbIds(true);
		for($retryCount=0; $retryCount<=$this->_connection->retryCount; ++$retryCount)
		{
			foreach($dbIds as $dbIndex=>$dbId)
			{
				try
				{
					$__starttime = microtime(true);
					$this->_statement=$this->_connection->getPdoInstance($dbId)->prepare($this->getText());
					if($params===array())
						$this->_statement->execute();
					else
						$this->_statement->execute($params);

					if($method==='')
						$result=new CDbDataReader($this);
					else
					{
						$mode=(array)$mode;
						$result=call_user_func_array(array($this->_statement, $method), $mode);
						$this->_statement->closeCursor();
					}
					Mod::trace('Querying SQL Time:'.round(microtime(true)-$__starttime,4),'system.db.CDbCommand');
					$this->__nsfeedback($__starttime,$dbId);
					if($this->_connection->enableProfiling)
						Mod::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

					if(isset($cache,$cacheKey))
						$cache->set($cacheKey, $result, $this->_connection->queryCachingDuration, $this->_connection->queryCachingDependency);
                    if($this->_connection->enablePerformReport){
                        // end perform report
                        $report->addParam('skeys',$this->getText());
                        $re = $report->endPerfReport(0);
                    }
					return $result;
				}
				catch(Exception $e)
				{
					//说明这个数据库链接坏了，关闭数据库链接，下次重新创建链接
					$this->_connection->close($dbId);
					
					$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
					$message = $e->getMessage();
					Mod::log(Mod::t('mod','CDbCommand::{method}() failed: {error}. The SQL statement executed was: {sql}.',array('{method}'=>$method, '{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.CDbCommand');
					
					//记录失败日志，需要区分是否为重试
					if($retryCount||$dbIndex)
						Mod::log("sql query fail(dbId:$dbId,retryCount:$retryCount,sql:".$this->getText().$par.')', CLogger::LEVEL_ERROR, 'system.db.CDbCommand');
					else
						Mod::log("sql query fail(dbId:$dbId,sql:".$this->getText().$par.')', CLogger::LEVEL_ERROR, 'system.db.CDbCommand');
					
					//判断是否需要sleep
					if($this->_connection->retryCount && $this->_connection->retryInterval && $retryCount<$this->_connection->retryCount)
						usleep($this->_connection->retryInterval);
				}
			}
		}
		
		//执行sql失败，抛异常。
		if($this->_connection->enableProfiling)
			Mod::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');
		if($this->_connection->enablePerformReport){
            $report->addParam('skeys',$this->getText().$par);
            $re = $report->endPerfReport(0);
        }
        if(MOD_DEBUG)
			$message .= '. The SQL statement executed was: '.$this->getText().$par;
		throw new CDbException(Mod::t('mod','CDbCommand failed to execute the SQL statement: {error}',array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
	}
	
	private function __nsfeedback($__starttime, $dbid)
	{
		$dbcfg = $this->_connection->getConConfig ($dbid);
		if (! empty ( $dbcfg['ns'] )) {
			// if use name service , feedback usetime
			$__ns = $this->_connection->getNS ();
			if(method_exists($__ns, "feedback"))
			{
				$__cm = $this->__mapConnStr ( $dbcfg );
				$__usetime = intval ((microtime ( true ) - $__starttime) * 1000 );
				$__ns->feedback ($dbcfg['ns'],
						$__cm ['host'], $__cm ['port'], $__usetime );
			}
		}
	}
	
	public function genDbCacheKey($params){
		$unisql = $this->_connection->connectionString.':'.$this->_connection->username;
		$unisql .= ':'.$this->getText().':'.serialize(array_merge($this->_paramLog,$params));
		$cacheKey='mod:dbquery:'.sha1($unisql);
		return $cacheKey;
	}
	
	private function __mapConnStr($connstr)
	{
		$tmp = substr(strrchr($connstr, ":"), 1);
		$arrs = explode(";",$tmp);
		$retarr = array();
		foreach($arrs as $a)
		{
			$p = explode("=",$a);
			if(count($p) !=2 ) continue;
			if($p[0]=="host" && $p[1]=="localhost") $p[1] = IP_LOCAL;
			$retarr[$p[0]] = $p[1];
		}
		if(!isset($retarr['port'])) $retarr ['port'] = '3306';
		return $retarr ;
	}
	
	public function buildQuery($query)
	{
		$sql=!empty($query['distinct']) ? 'SELECT DISTINCT' : 'SELECT';
		$sql.=' '.(!empty($query['select']) ? $query['select'] : '*');
	
		if(!empty($query['from']))
			$sql.="\nFROM ".$query['from'];
		else
			throw new CDbException(Mod::t('Mod','The DB query must contain the "from" portion.'));
	
		if(!empty($query['join']))
			$sql.="\n".(is_array($query['join']) ? implode("\n",$query['join']) : $query['join']);
	
		if(!empty($query['where']))
			$sql.="\nWHERE ".$query['where'];
	
		if(!empty($query['group']))
			$sql.="\nGROUP BY ".$query['group'];
	
		if(!empty($query['having']))
			$sql.="\nHAVING ".$query['having'];
	
		if(!empty($query['union']))
			$sql.="\nUNION (\n".(is_array($query['union']) ? implode("\n) UNION (\n",$query['union']) : $query['union']) . ')';
	
		if(!empty($query['order']))
			$sql.="\nORDER BY ".$query['order'];
	
		$limit=isset($query['limit']) ? (int)$query['limit'] : -1;
		$offset=isset($query['offset']) ? (int)$query['offset'] : -1;
		if($limit>=0 || $offset>0)
			$sql=$this->_connection->getCommandBuilder()->applyLimit($sql,$limit,$offset);
	
		return $sql;
	}
	
	public function limit($limit, $offset=null)
	{
		$this->_query['limit']=(int)$limit;
		if($offset!==null)
			$this->offset($offset);
		return $this;
	}
	
	public function offset($offset)
	{
		$this->_query['offset']=(int)$offset;
		return $this;
	}
	
	public function group($columns)
	{
		if(is_string($columns) && strpos($columns,'(')!==false)
			$this->_query['group']=$columns;
		else
		{
			if(!is_array($columns))
				$columns=preg_split('/\s*,\s*/',trim($columns),-1,PREG_SPLIT_NO_EMPTY);
			foreach($columns as $i=>$column)
			{
				if(is_object($column))
					$columns[$i]=(string)$column;
				elseif(strpos($column,'(')===false)
				$columns[$i]=$this->_connection->quoteColumnName($column);
			}
			$this->_query['group']=implode(', ',$columns);
		}
		return $this;
	}
	
	public function having($conditions, $params=array())
	{
		$this->_query['having']=$this->processConditions($conditions);
		foreach($params as $name=>$value)
			$this->params[$name]=$value;
		return $this;
	}
	
	public function order($columns)
	{
		if(is_string($columns) && strpos($columns,'(')!==false)
			$this->_query['order']=$columns;
		else
		{
			if(!is_array($columns))
				$columns=preg_split('/\s*,\s*/',trim($columns),-1,PREG_SPLIT_NO_EMPTY);
			foreach($columns as $i=>$column)
			{
				if(is_object($column))
					$columns[$i]=(string)$column;
				elseif(strpos($column,'(')===false)
				{
					if(preg_match('/^(.*?)\s+(asc|desc)$/i',$column,$matches))
						$columns[$i]=$this->_connection->quoteColumnName($matches[1]).' '.strtoupper($matches[2]);
					else
						$columns[$i]=$this->_connection->quoteColumnName($column);
				}
			}
			$this->_query['order']=implode(', ',$columns);
		}
		return $this;
	}
	
	public function union($sql)
	{
		if(isset($this->_query['union']) && is_string($this->_query['union']))
			$this->_query['union']=array($this->_query['union']);

		$this->_query['union'][]=$sql;

		return $this;
	}


}
