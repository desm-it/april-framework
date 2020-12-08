<?php
/**
 * Queryable Database class - Do not use directly, only use its derived classes such as {@link Database\MySQL}.
 * @abstract
 */
namespace April\Project\Database;
abstract class Queryable {

	/**
	 * @ignore
	 * @return Database\MySQL\Result
	 */
	abstract public function executeQuery($sQuery_);
}
?>