<?php
/**
 * A database class
 *
 * @version $Id$
 * @author  Matt McNaney <matt at tux dot appstate dot edu>
 * @package Core
 */
require_once 'DB.php';

// Changing LOG_DB to TRUE will cause ALL DB traffic to get logged
// This can log can get very large, very fast. DO NOT turn it on
// on a live server. It is for development purposes only.
define ('LOG_DB', FALSE);

define ('DEFAULT_MODE', DB_FETCHMODE_ASSOC);

class PHPWS_DB {
    var $tables      = NULL;
    var $where       = array();
    var $order       = array();
    var $values      = array();
    var $mode        = DEFAULT_MODE;
    var $limit       = NULL;
    var $index       = NULL;
    var $columns     = NULL;
    var $qwhere      = NULL;
    var $indexby     = NULL;
    var $groupby     = NULL;
    var $sql         = NULL;
    var $_allColumns = NULL;
    var $_columnInfo = NULL;
    var $_lock       = FALSE;
    var $_sql        = NULL;
    var $_distinct   = TRUE;

    function PHPWS_DB($table=NULL){
        PHPWS_DB::touchDB();
        if (isset($table)){
            $result = $this->setTable($table);

            if (PEAR::isError($result)) {
                PHPWS_Error::log($result);
            }
        }
        $this->setMode('assoc');
        $type = $GLOBALS['PEAR_DB']->dbsyntax;

        $result = PHPWS_Core::initCoreClass('DB/' . $type .'.php');
        if ($result == FALSE) {
            PHPWS_Error::log(PHPWS_FILE_NOT_FOUND, 'core', 'PHPWS_DB::PHPWS_DB', 
                             PHPWS_SOURCE_DIR . 'core/class/DB/' . $type . '.php');
            PHPWS_Core::errorPage();
        }
        $this->_sql = & new PHPWS_SQL;
    }

    /**
     * Lets you enter a raw select query
     */ 
    function setSQLQuery($sql)
    {
        $this->sql = $sql;
    }

    function lock()
    {
    
    }

    function unlock()
    {

    }

    function touchDB(){
        if (!PHPWS_DB::isConnected()) {
            PHPWS_DB::loadDB();
        }
    }

    function isConnected(){
        if (isset($GLOBALS['PEAR_DB']))
            return TRUE;
        else
            return FALSE;
    }

    function loadDB($dsn=NULL){
        if (PHPWS_DB::isConnected())
            PHPWS_DB::disconnect();

        if (isset($dsn)) {
            $GLOBALS['PEAR_DB'] = DB::connect($dsn);
        } else {
            $GLOBALS['PEAR_DB'] = DB::connect(PHPWS_DSN);
        }

        if (PEAR::isError($GLOBALS['PEAR_DB'])){
            PHPWS_Error::log($GLOBALS['PEAR_DB']);
            PHPWS_Core::errorPage();
        }

        if (defined(TABLE_PREFIX))
            PHPWS_DB::setPrefix(TABLE_PREFIX);
        else
            PHPWS_DB::setPrefix(NULL);

        return TRUE;
    }

    function logDB($sql)
    {
        if (!defined('LOG_DB') || LOG_DB != TRUE) {
            return;
        }

        PHPWS_Core::log($sql, 'db.log');
    }

    function query($sql, $prefix=FALSE){
        PHPWS_DB::touchDB();
        if ($prefix == TRUE) {
            $sql = PHPWS_DB::prefixTable($sql);
        }

        PHPWS_DB::logDB($sql);
        return $GLOBALS['PEAR_DB']->query($sql);
    }

    function getColumnInfo($col_name, $parsed=FALSE){
        if (!isset($this->_columnInfo))
            $this->getTableColumns();

        if (isset($this->_columnInfo[$col_name])) {
            if ($parsed == TRUE)
                return $this->parsePearCol($this->_columnInfo[$col_name], TRUE);
            else
                return $this->_columnInfo[$col_name];
        }
        else
            return NULL;
    }

    function inDatabase($table, $column=NULL)
    {
        PHPWS_DB::touchDB();
        static $database_info = NULL;

        $column = trim($column);
        $answer = FALSE;

        if (!empty($database_info[$table])) {
            if (empty($column)) {
                return TRUE;
            } else {
                return in_array($column, $database_info[$table]);
            }
        }

        $result = $GLOBALS['PEAR_DB']->tableInfo(strip_tags($table));
        if (PEAR::isError($result)) {
            if ($result->getCode() == DB_ERROR_NEED_MORE_DATA) {
                return FALSE;
            } else {
                return $result;
            }
        }

        foreach ($result as $colInfo) {
            $list_columns[] = $colInfo['name'];

            if ($colInfo['name'] == $column) {
                $answer = TRUE;
            }
        }

        $database_info[$table] = $list_columns;

        return $answer;
    }

    function getTableColumns($fullInfo=FALSE){
        if (isset($this->_allColumns) && $fullInfo == FALSE) {
            return $this->_allColumns;
        } elseif (isset($this->_columnInfo) && $fullInfo == TRUE) {
            return $this->_columnInfo;
        }

        $table = & $this->getTable();
        if (!isset($table)) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::isTableColumn');
        }

        $columns =  $GLOBALS['PEAR_DB']->tableInfo($table);
        if (PEAR::isError($columns))
            return $columns;

        foreach ($columns as $colInfo) {
            $this->_columnInfo[$colInfo['name']] = $colInfo;
            $this->_allColumns[] = $colInfo['name'];
        }

        if ($fullInfo == TRUE) {
            return $this->_columnInfo;
        } else {
            return $this->_allColumns;
        }
    }

    function isTableColumn($columnName){
        $columns = $this->getTableColumns();

        if (PEAR::isError($columns)) {
            return $columns;
        }

        return in_array($columnName, $columns);
    }

    function setMode($mode){
        switch (strtolower($mode)){
        case 'ordered':
            $this->mode = DB_FETCHMODE_ORDERED;
            break;

        case 'object':
            $this->mode = DB_FETCHMODE_OBJECT;
            break;

        case 'assoc':
            $this->mode = DB_FETCHMODE_ASSOC;
            break;
        }

    }

    function getMode(){
        return $this->mode;
    }

    function isTable($tableName){
        static $tables;

        if (count($tables) < 1){
            PHPWS_DB::touchDB();
            $tables = PHPWS_DB::listTables();
        }

        return in_array(PHPWS_DB::getPrefix() . $tableName, $tables);
    }

    function listTables(){
        return $GLOBALS['PEAR_DB']->getlistOf('tables');
    }

    function listDatabases(){
        return $GLOBALS['PEAR_DB']->getlistOf('databases');
    }


    function setPrefix($prefix){
        $GLOBALS['PEAR_DB']->prefix = $prefix;
    }

    function getPrefix(){
        return $GLOBALS['PEAR_DB']->prefix;
    }

    function addTable($table)
    {
        if (is_array($table)) {
            foreach ($table as $tbl_name) {
                $this->addTable($tbl_name);
            }
            return;
        }

        if (PHPWS_DB::allowed($table) && !in_array($table, $this->tables)) {
            $this->tables[] = $table;
        }
        else {
            return PHPWS_Error::get(PHPWS_DB_BAD_TABLE_NAME, 'core', 'PHPWS_DB::setTable', $table);
        }
        return TRUE;
    }

    function setTable($table)
    {
        $this->tables = array();
        return $this->addTable($table);
    }

    function setIndex($index){
        $this->index = $index;
    }

    function getIndex(){
        if (isset($this->index)) {
            return $this->index;
        }

        $columns =  $GLOBALS['PEAR_DB']->tableInfo($this->getTable());
    
        if (PEAR::isError($columns))
            return $columns;
    
        foreach ($columns as $colInfo)
            if ($colInfo['name'] == 'id' && preg_match('/primary/', $colInfo['flags']) && preg_match('/int/', $colInfo['type']))
                return $colInfo['name'];

        return NULL;
    }

    function getTable($format=TRUE, $prefix=TRUE){
        if ($format == TRUE) {
            if ($prefix == TRUE) {
                array_walk($this->tables, '_add_tbl_prefix', PHPWS_DB::getPrefix());
            }
            return implode(', ', $this->tables);
        } else {
            return $this->tables;
        }
    }

    function resetTable(){
        $this->tables = array();
    }

    function setGroupConj($group, $conj)
    {
        $conj = strtoupper($conj);
        if (empty($conj) || ($conj != 'OR' &&  $conj != 'AND')) {
            return FALSE;
        }

        $this->where[$group]['conj'] = $conj;
    }

    function addGroupBy($group_by){
        if (PHPWS_DB::allowed($group_by))
            $this->groupBy[] = $group_by;
    }

    function getGroupBy($dbReady=FALSE){
        if ((bool)$dbReady == TRUE){
            if (empty($this->groupBy)) {
                return NULL;
            } else {
                return 'GROUP BY ' . implode(', ', $this->groupBy);
            }
        }
        return $this->groupBy;
    }

    function addJoinWhere($column, $value=NULL, $operator=NULL,
                          $conj=NULL, $group=NULL)
    {
        return $this->addWhere($column, $value, $operator, $conj, $group, TRUE);
    }

    function addWhere($column, $value=NULL, $operator=NULL, $conj=NULL,
                      $group=NULL, $join=FALSE)
    {
        $where = & new PHPWS_DB_Where;
        $where->setJoin($join);
        $operator = strtoupper($operator);
        if (is_array($column)) {
            foreach ($column as $new_column => $new_value) {
                $result = $this->addWhere($new_column, $new_value);
                if (PEAR::isError($result)) {
                    return $result;
                }
            }
            return TRUE;
        } else {
            if (!PHPWS_DB::allowed($column)) {
                return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core',
                                        'PHPWS_DB::addWhere', $column);
            }
        }

        if (is_array($value) && !empty($value)) {
            if (!empty($operator) && $operator != 'IN' && $operator != 'BETWEEN') {
                $search_in = TRUE;
            } else {
                if (empty($operator)) {
                    $operator = 'IN';
                }
                $search_in = FALSE;
            }
            
            foreach ($value as $newVal){
                if ($search_in) {
                    $result = $this->addWhere($column, $newVal, $operator, $conj, $group);
                    if (PEAR::isError($result)) {
                        return $result;
                    }
                } else {
                    $newVal = $GLOBALS['PEAR_DB']->escapeSimple($newVal);
                    $new_value_list[] = $newVal;
                }
            }

            if (!$search_in && isset($new_value_list)) {
                $value = &$new_value_list;
            } else {
                return TRUE;
            }
        } else {
            if (is_null($value) || strtoupper($value) == 'NULL') {
                if (empty($operator) || ( $operator != 'IS NOT' && $operator != '!=')) {
                    $operator = 'IS';
                } else {
                    $operator = 'IS NOT';
                }
                $value = 'NULL';
            } else {
                $value = $GLOBALS['PEAR_DB']->escapeSimple($value);
            }
        }

        $source_table = $this->tables[0];

	if (is_string($column)) {
            if (substr_count($column, '.') == 1) {
                list($join_table, $join_column) = explode('.', $column);
                if (PHPWS_DB::inDatabase($join_table, $join_column)) {
                    $column = &$join_column;
                    $source_table = $join_table;
                    $where->setTable($join_table);
                    $this->addTable($join_table);
                }
            }
	}

        $where->setColumn($column);
        $where->setTable($source_table);

	if (is_string($value)) {
            if (substr_count($value, '.') == 1) {
                list($join_table, $join_column) = explode('.', $value);
                if (PHPWS_DB::inDatabase($join_table, $join_column)) {
                    $where->setJoin(TRUE);
                    $this->addTable($join_table);
                }
            }
	}

        $where->setValue($value);

        $where->setConj($conj);
        $where->setOperator($operator);


        if (isset($group)) {
            $this->where[$group]['values'][] = $where;
        }
        else {
            $this->where[0]['values'][] = $where;
        }

    }

    function checkOperator($operator){
        $allowed = array('>',
                         '>=',
                         '<',
                         '<=',
                         '=',
                         '!=',
                         '<>',
                         '<=>',
                         'LIKE',
                         'REGEXP',
                         'IN',
                         'BETWEEN',
                         'IS',
                         'IS NOT');

        return in_array(strtoupper($operator), $allowed);
    }

    function setQWhere($where, $conj='AND'){
        $conj = strtoupper($conj);
        if (empty($conj) || ($conj != 'OR' &&  $conj != 'AND')) {
            return FALSE;
        }
        
        $where = preg_replace('/where/i', '', $where);
        $this->qwhere['where'] = $where;
        $this->qwhere['conj']  = $conj;
    }

    function getWhere($dbReady=FALSE){
        $where = NULL;

        if (empty($this->where)){
            if (isset($this->qwhere)) {
                return $this->qwhere['conj'] . ' (' . $this->qwhere['where'] .')';
            }
            return NULL;
        }

        $startMain = FALSE;
        if ($dbReady){
            foreach ($this->where as $group_name => $groups) {

                if (!isset($groups['values'])) {
                    continue;
                }
                $startSub = FALSE;

                if ($startMain == TRUE) {
                    if (empty($groups['conj'])) {
                        $sql[] = ' AND ';
                    } else {
                        $sql[] = $groups['conj'];
                    }
                }
                $sql[] = '(';


                foreach ($groups['values'] as $whereVal){
                    if ($startSub == TRUE) {
                        $sql[] = $whereVal->conj;
                    }
                    $sql[] = $whereVal->get();
                    $startSub = TRUE;
                }

                $sql[] = ')';
                $startMain = TRUE;

            }

            if (isset($this->qwhere)) {
                $sql[] = $this->qwhere['conj'] . ' (' . $this->qwhere['where'] . ')';
            }


            if (isset($sql)) {
                $where = implode(' ', $sql);
            }

            return $where;
        } else {
            return $this->where;
        }
    }

    function resetWhere(){
        $this->where = array();
    }

    function isDistinct(){
        $this->_distinct = TRUE;
    }

    function notDistinct(){
        $this->_distinct = FALSE;
    }

    function addColumn($column, $distinct=FALSE, $max_min=NULL)
    {
        if (!in_array(strtolower($max_min), array('max', 'min'))) {
            $max_min = NULL;
        }

        $table = $this->tables[0];
        if (strstr($column, '.')) {
            list($table, $column) = explode('.', $column);
            $this->addTable($table);
        }

        if (!PHPWS_DB::allowed($column)) {
            return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addColumn', $column);
        }

        $col['table']    = $table;
        $col['name']     = $column;
        $col['distinct'] = $distinct;
        $col['max_min']  = $max_min;

        $this->columns[] = $col;
    }

    function getAllColumns()
    {
        $columns[] = $this->getColumn(TRUE);
        return $columns;
    }

    function getColumn($format=FALSE){
        if ($format) {
            if (empty($this->columns)) {
                return $this->tables[0] . '.*';
            } else {
                foreach ($this->columns as $col) {
                    extract($col);
                    if ($distinct) {
                        $dist = 'DISTINCT ';
                    } else {
                        $dist = NULL;
                    }
                    if ($max_min) {
                        $columns[] = $dist . strtoupper($max_min) . "($table.$name)";
                    } else {
                        $columns[] = "$dist$table.$name";
                    }
                }
                return implode(', ', $columns);
            }
        } else {
            return $this->columns;
        }
    }

    function setIndexBy($indexby){
        $this->indexby = $indexby;
    }

    function getIndexBy(){
        return $this->indexby;
    }


    function addOrder($order){
        if (is_array($order)){
            foreach ($order as $value){
                $this->order[] = preg_replace('/[^\w\s]/', '', $value);
            }
        }
        else
            $this->order[] = preg_replace('/[^\w\s]/', '', $order);
    }

    function getOrder($dbReady=FALSE){
        if (!count($this->order))
            return NULL;

        if ($dbReady)
            return 'ORDER BY ' . implode(', ', $this->order);
        else
            return $this->order;
    }

    function resetOrder(){
        $this->order = array();
    }

    function addValue($column, $value=NULL){
        if (is_array($column)){
            foreach ($column as $colKey=>$colVal){
                $result = $this->addValue($colKey, $colVal);
                if (PEAR::isError($result))
                    return $result;
            }
        } else {
            if (!PHPWS_DB::allowed($column))
                return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addValue', $column);

            $this->values[$column] = $value;
        }
    }

    function getValue($column){
        if (!count($this->values) || !isset($this->values[$column]))
            return NULL;

        return $this->values[$column];
    }

    function resetValues(){
        $this->values = array();
    }

    function getAllValues(){
        if (!isset($this->values) || !count($this->values))
            return NULL;

        return $this->values;
    }


    function setLimit($limit, $offset=NULL){
        unset($this->limit);

        if (is_array($limit)) {
            $_limit = $limit[0];
            $_offset = $limit[1];
        }
        elseif (preg_match('/,/', $limit)) {
            $split = explode(',', $limit);
            $_limit = trim($split[0]);
            $_offset = trim($split[1]);
        }
        else {
            $_limit = $limit;
            $_offset = $offset;
        }

        $this->limit['total'] = preg_replace('/[^\d\s]/', '', $_limit);

        if (isset($_offset))
            $this->limit['offset'] = preg_replace('/[^\d\s]/', '', $_offset);

        return TRUE;
    }

    function getLimit($dbReady=FALSE){
        if (empty($this->limit))
            return NULL;
    
        if ($dbReady) {
            return $this->_sql->getLimit($this->limit);
        }
        else {
            return $this->limit;
        }
    }

    function resetLimit(){
        $this->limit = '';
    }

    function resetColumns(){
        $this->columns = NULL;
    }


    function affectedRows(){
        $query =  PHPWS_DB::lastQuery();
        $process = strtolower(substr($query, 0, strpos($query, ' ')));

        if ($process == 'select') {
            return FALSE;
        }

        return $GLOBALS['PEAR_DB']->affectedRows();
    }

    function reset(){
        $this->resetWhere();
        $this->resetValues();
        $this->resetLimit();
        $this->resetOrder();
        $this->resetColumns();
        $this->indexby = NULL;
        $this->qwhere  = NULL;
    }

    function lastQuery(){
        return $GLOBALS['PEAR_DB']->last_query;
    }

    function insert(){
        $maxID = TRUE;
        $table = $this->getTable();
        if (!$table) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::insert');
        }

        $values = $this->getAllValues();

        if (!isset($values))
            return PHPWS_Error::get(PHPWS_DB_NO_VALUES, 'core', 'PHPWS_DB::insert');

        $idColumn = $this->getIndex();

        if (PEAR::isError($idColumn)) {
            return $idColumn;
        } elseif(isset($idColumn)) {
            $maxID = $GLOBALS['PEAR_DB']->nextId($table);
            $values[$idColumn] = $maxID;
        }

        foreach ($values as $index=>$entry){
            $columns[] = $index;
            $set[] = PHPWS_DB::dbReady($entry);
        }

        $query = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $set) . ')';

        $result = PHPWS_DB::query($query);

        if (DB::isError($result)) {
            return $result;
        } else {
            return $maxID;
        }
    }

    function update(){
        $table = $this->getTable();
        if (!$table) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::update');
        }

        $values = $this->getAllValues();
        $where = $this->getWhere(TRUE);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }

        if (empty($values)) {
            return PHPWS_Error::get(PHPWS_DB_NO_VALUES, 'core', 'PHPWS_DB::update');
        }

        foreach ($values as $index=>$data) {
            $columns[] = $index . ' = ' . PHPWS_DB::dbReady($data);
        }

        $query = "UPDATE $table SET " . implode(', ', $columns) ." $where";
        $result = PHPWS_DB::query($query);

        if (DB::isError($result))
            return $result;
        else
            return TRUE;
    }

    function count(){
        return $this->select('count');
    }

    function getSelectSQL($type){
        if ($type == 'count' && empty($this->columns)) {
            $columns = NULL;
        } else {
            $columns = implode(', ', $this->getAllColumns());
        }

        $table = $this->getTable();

        if (!$table) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::select');
        }

        $where   = $this->getWhere(TRUE);
        $order   = $this->getOrder(TRUE);
        $limit   = $this->getLimit(TRUE);
        $groupby = $this->getGroupBy(TRUE);

        $sql_array['columns'] = &$columns;
        $sql_array['table']   = &$table;
        $sql_array['where']   = &$where;
        $sql_array['groupby'] = &$groupby;
        $sql_array['order']   = &$order;
        $sql_array['limit']   = &$limit;

        return $sql_array;
    }

    function select($type=NULL, $sql=NULL)
    {
        if (empty($sql)) {
            if (!empty($this->sql)) {
                $sql = & $this->sql;
            }
        }
        PHPWS_DB::touchDB();
        if (isset($type)) {
            $type = strtolower($type);
        }

        $mode = $this->getMode();
        $indexby = $this->getIndexBy();

        if (!isset($sql)){
            $sql_array = $this->getSelectSQL($type);
            if (PEAR::isError($sql_array)) {
                return $sql_array;
            }

            // extract will get $columns, $table, $where, $groupby
            // $order, and $limit
            extract($sql_array);

            if ($type == 'count') {
                if (empty($columns)) {
                    $columns = 'COUNT(*)';
                } else {
                    $add_group = $columns;
                    $columns .= ', COUNT(*)';   
                    if (empty($groupby)) {
                        $groupby = "GROUP BY $add_group";
                    } else {
                        $groupby .= ", $addgroup";
                    }
                }
            }
            if (!empty($where)) {
                $where = 'WHERE ' . $where;
            }
            $sql = "SELECT $columns FROM $table $where $groupby $order $limit";
        } else {
            $mode = DB_FETCHMODE_ASSOC;
        }

        // assoc does odd things if the resultant return is two items or less
        // not sure why it is coded that way. Use the default instead

        switch ($type){
        case 'assoc':
            return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAssoc($sql, NULL,NULL, $mode), $type);
            break;

        case 'col':
            if (empty($this->columns))
                return PHPWS_Error::get(PHPWS_DB_NO_COLUMN_SET, 'core', 'PHPWS_DB::select');

            if (isset($indexby)){
                PHPWS_DB::logDB($sql);
                $result = PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAll($sql, NULL, $mode), $type);
                if (PEAR::isError($result))
                    return $result;

                return PHPWS_DB::_indexBy($result, $indexby, TRUE);
            }
            PHPWS_DB::logDB($sql);
            return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getCol($sql), $type);
            break;

        case 'min':
        case 'max':
        case 'one':
            PHPWS_DB::logDB($sql);
            $value = $GLOBALS['PEAR_DB']->getOne($sql, NULL, $mode);
            db_trim($value);
            return $value;
            break;

        case 'row':
            PHPWS_DB::logDB($sql);
            return PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getRow($sql, array(), $mode), $type);
            break;

        case 'count':
            $result = $this->getAll($sql);

            if (PEAR::isError($result)){
                return $result;
            }

            if (empty($result)) {
                return 0;
            }

            if (count($result) > 1) {
                return $result;
            }
            else {
                list(, $count_val) = each($result[0]);
                return $count_val;
            }
            break;

        case 'all':
        default:
            PHPWS_DB::logDB($sql);
            $result = PHPWS_DB::autoTrim($GLOBALS['PEAR_DB']->getAll($sql, NULL, $mode), $type);
            if (PEAR::isError($result))
                return $result;

            if (isset($indexby))
                return PHPWS_DB::_indexBy($result, $indexby);

            return $result;
            break;
        }
    }

    function getRow($sql)
    {
        $db = & new PHPWS_DB;
        return $db->select('row', $sql);
    }

    function getCol($sql)
    {
        $db = & new PHPWS_DB;
        return $db->select('col', $sql);
    }

    function getAll($sql)
    {
        $db = & new PHPWS_DB;
        return $db->select('all', $sql);
    }

    function getAssoc($sql)
    {
        $db = & new PHPWS_DB;
        return $db->select('assoc', $sql);
    }

    function _indexBy($sql, $indexby, $colMode=FALSE){
        if (!is_array($sql))
            return $sql;
        $stacked = FALSE;

        foreach ($sql as $item){
            if (!isset($item[(string)$indexby]))
                return $sql;

            if ($colMode){
                $col = $this->getColumn();
                PHPWS_DB::_expandIndex($rows, $item[$indexby], $item[$col[0]], $stacked);
            } else
                PHPWS_DB::_expandIndex($rows, $item[$indexby], $item, $stacked);
        }

        return $rows;
    }

    function _expandIndex(&$rows, $index, $item, &$stacked){
        if (isset($rows[$index])) {
            if (!$stacked) {
                $hold = $rows[$index];
                $rows[$index] = array();
                $rows[$index][] = $hold;
                $stacked = TRUE;
            }
            $rows[$index][] = $item;
        } else {
            $rows[$index] = $item;
        }
    }


    function delete(){
        $table = $this->getTable();
        if (!$table) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::delete');
        }

        $where = $this->getWhere(TRUE);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }
        $sql = "DELETE FROM $table $where";
        return PHPWS_DB::query($sql);
    }
  

    function dropTable(){
        $table = $this->getTable();
        if (!$table)
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::dropTable');

        $sql = "DROP TABLE $table";

        return PHPWS_DB::query($sql);
    }

    function createTable(){
        $table = $this->getTable();
        if (!$table)
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::createTable');

        $values = $this->getAllValues();

        foreach ($values as $column=>$value)
            $parameters[] = $column . ' ' . $value;


        $sql = "CREATE TABLE $table ( " . implode(', ', $parameters) . ' )';
        return PHPWS_DB::query($sql);
    }

    function addTableColumn($column, $parameter, $after=NULL, $indexed=FALSE){
        $table = $this->getTable();
        if (!$table) {
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::addColumn');
        }

        if (!PHPWS_DB::allowed($column)) {
            return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::addTableColumn', $column);
        }

        if (isset($after)){
            if (strtolower($after) == 'first')
                $location = 'FIRST';
            else
                $location = "AFTER $after";
        } else
            $location = NULL;

        $sql = "ALTER TABLE $table ADD $column $parameter $location";

        $result = PHPWS_DB::query($sql);
        if (PEAR::isError($result))
            return $result;

        if ($indexed == TRUE){
            $indexSql = "CREATE INDEX $column on $table($column)";
            $result = PHPWS_DB::query($indexSql);
            if (PEAR::isError($result))
                return $result;
        }
        return TRUE;
    }


    function dropTableColumn($column){
        $table = $this->getTable();
        if (!$table)
            return PHPWS_Error::get(PHPWS_DB_ERROR_TABLE, 'core', 'PHPWS_DB::dropColumn');

        if (!PHPWS_DB::allowed($column))
            return PHPWS_Error::get(PHPWS_DB_BAD_COL_NAME, 'core', 'PHPWS_DB::dropTableColumn', $column);

        $sql = "ALTER TABLE $table DROP $column";

        return PHPWS_DB::query($sql);
    }


    function getDBType(){
        return $GLOBALS['PEAR_DB']->phptype;
    }


    function disconnect(){
        if (PHPWS_DB::isConnected())
            $GLOBALS['PEAR_DB']->disconnect();
    }

    function importFile($filename){
        if (!is_file($filename)) {
            return PHPWS_Error::get(PHPWS_FILE_NOT_FOUND, 'core', 'PHPWS_DB::importFile');
        }
        $data = file_get_contents($filename);
        return $this->import($data);
    }

    function import($text, $report_errors=TRUE){
        PHPWS_DB::touchDB();

        $prefix = PHPWS_DB::getPrefix();
        $sqlArray = PHPWS_Text::sentence($text);

        foreach ($sqlArray as $sqlRow){
            if (empty($sqlRow) || preg_match("/^[^\w\d\s\\(\)]/i", $sqlRow))
                continue;

            $sqlCommand[] = $sqlRow;

            if (preg_match("/;$/", $sqlRow)){
                $query = implode(' ', $sqlCommand);

                if (isset($prefix)){
                    $tableName = PHPWS_DB::extractTableName($query);
                    $query = str_replace($tableName, $prefix . $tableName, $query);
                }
                $sqlCommand = array();

                PHPWS_DB::homogenize($query);

                $result = PHPWS_DB::query($query);
                if (DB::isError($result))
                    $errors[] = $result;
            }
        }

        if (isset($errors)) {
            if ($report_errors) {
                return $errors;
            } else {
                foreach ($errors as $_error){
                    PHPWS_Error::log($_error);
                }
                return FALSE;
            }
        }
        else
            return TRUE;
    }


    function homogenize(&$query){
        $query_list = explode(',', $query);

        $from = array('/int\(\d+\)/iU',
                      '/mediumtext/'
                      );
        $to = array('int',
                    'text'
                    );

        foreach ($query_list as $command) {
            if (preg_match ('/(smallint|int)\s/', $command)) {
                if(!preg_match('/\sdefault/i', $command)) {
                    $command = preg_replace('/int/', 'int default 0', $command);
                }

                if(!preg_match('/\snull/', $command)) {
                    $command = preg_replace('/int/', 'int not null', $command);
                }

            }

            $command = preg_replace($from, $to, $command);
            $newlist[] = $command;
        }

        $query = implode(',', $newlist);

        $this->_sql->readyImport($query);
    }


    function parsePearCol($info, $strip_name=FALSE){
        $setting = $this->_sql->export($info);

        if (isset($info['flags'])){
            if (stristr($info['flags'], 'multiple_key')){
                $column_info['index'] = 'CREATE INDEX ' .  $info['name'] . ' on ' . $info['table'] 
                    . '(' . $info['name'] . ')';
                $info['flags'] = str_replace(' multiple_key', '', $info['flags']);
            }
            $preFlag = array('/not_null/', '/primary_key/', '/default_(.*)?/', '/blob/');
            $postFlag = array('NOT NULL', 'PRIMARY KEY', "DEFAULT '\\1'", '');
            $multipleFlag = array('multiple_key', '');
            $flags = ' ' . preg_replace($preFlag, $postFlag, $info['flags']);
        }
        else
            $flags = NULL;
    
        if ($strip_name == TRUE) {
            $column_info['parameters'] = $setting . $flags; 
        }
        else {
            $column_info['parameters'] = $info['name'] . " $setting" . $flags; 
        }

        return $column_info;
    }

    function parseColumns($columns){
        foreach ($columns as $info){
            if (!is_array($info))
                continue;

            $result = $this->parsePearCol($info);
            if (isset($result['index']))
                $column_info['index'][] = $result['index'];

            $column_info['parameters'][] = $result['parameters'];
        }

        return $column_info;
    }

    function export($structure=TRUE, $contents=TRUE){
        PHPWS_DB::touchDB();

        if ($structure == TRUE) {
            $columns =  $GLOBALS['PEAR_DB']->tableInfo($this->table);

            $column_info = $this->parseColumns($columns);
            $index = $this->getIndex();

            if ($prefix = PHPWS_DB::getPrefix())
                $tableName = str_replace('', $prefix, $tableName);

            $sql[] = "CREATE TABLE $tableName ( " .  implode(', ', $column_info['parameters']) .' );';
            if (isset($column_info['index']))
                $sql = array_merge($sql, $column_info['index']);
        }

        if ($contents == TRUE){
            if ($rows = $this->select()){
                if (PEAR::isError($rows))
                    return $rows;
                foreach ($rows as $dataRow){
                    foreach ($dataRow as $key=>$value){
                        $allKeys[] = $key;
                        $allValues[] = PHPWS_DB::quote($value);
                    }
          
                    $sql[] = "INSERT INTO $tableName (" . implode(', ', $allKeys) . ') VALUES (' . implode(', ', $allValues) . ');';
                    $allKeys = $allValues = array();
                }
            }
        }

        return implode("\n", $sql);
    }

    function quote($text){
        return $GLOBALS['PEAR_DB']->quote($text);
    }

    function prefixTable($sql)
    {
        $tablename = PHPWS_DB::extractTableName($sql);
        return str_replace($tablename, PHPWS_DB::getPrefix() . $tablename, $sql);
    }

    function extractTableName($sql_value){
        $temp = explode(' ', trim($sql_value));
        
        if (!is_array($temp)) {
            return NULL;
        }
        foreach ($temp as $whatever){
            if (empty($whatever)) {
                continue;
            }
            $format[] = $whatever;
        }

        if (empty($format)) {
            return NULL;
        }

        switch (trim(strtolower($format[0]))) {
        case 'insert':
            if (stristr($format[1], 'into'))
                return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[2]));
            else
                return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[1]));
            break;
        
        case 'update':
            return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[1]));
            break;
      
        case 'select':
        case 'show':
            return preg_replace('/\(+.*$/', '', str_replace('`', '', $format[3]));
            break;

        case 'drop':
        case 'alter':
            return preg_replace('/;/', '', str_replace('`', '', $format[2]));
            break;

        default:
            return preg_replace('/\W/', '', $format[2]);
            break;
        }
    }// END FUNC extractTableName


    /**
     * Prepares a value for database writing or reading
     *
     * @author Matt McNaney <matt at NOSPAM dot tux dot appstate dot edu>
     * @param  mixed $value The value to prepare for the database.
     * @return mixed $value The prepared value
     * @access public
     */
    function dbReady($value=NULL) {
        if (is_array($value) || is_object($value)) {
            return PHPWS_DB::dbReady(serialize($value));
        } elseif (is_string($value)) {
            return "'" . $GLOBALS['PEAR_DB']->escapeSimple($value) . "'";
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return ($value ? 1 : 0);
        } else {
            return $value;
        }
    }// END FUNC dbReady()

    /**
     * @author Matt McNaney <matt at tux dot appstate dot edu>
     * @param  object $object        Object variable filled with result.
     * @param  object $require_where If TRUE, require a where parameter or
     *                               have the id set
     * @return boolean               Returns TRUE if plugObject is successful
     * @access public
     */
    function loadObject(&$object, $require_where=TRUE){
        if (!is_object($object)) {
            return PHPWS_Error::get(PHPWS_DB_NOT_OBJECT, 'core', 'PHPWS_DB::loadObject');
        }

        if ($require_where && empty($object->id) && empty($this->where)) {
            return PHPWS_Error::get(PHPWS_DB_NO_ID, 'core', 'PHPWS_DB::loadObject');
        }

        if ($require_where && empty($this->where)) {
            $this->addWhere('id', $object->id);
        }

        $variables = $this->select('row');

        if (PEAR::isError($variables)) {
            return $variables;
        } elseif (empty($variables)) {
            return NULL;
        }

        return PHPWS_Core::plugObject($object, $variables);
    }// END FUNC loadObject

    /**
     * Creates an array of objects contructed from the submitted
     * class name.
     *
     * Use this function instead of select() to get an array of objects.
     * Note that your class variables and column names MUST match exactly.
     * Unmatched pairs will be ignored.
     * @author Matthew McNaney <matt at tux dot appstate dot edu>
     * @param string $className Name of object class
     * @return array $items     Array of objects
     * @access public
     */
    function getObjects($className){
        if (!class_exists($className))
            return PHPWS_Error::get(PHPWS_CLASS_NOT_EXIST, 'core', 'PHPWS_DB::getObjects');

        $items = NULL;
        $result = $this->select();

        if (PEAR::isError($result) || !isset($result))
            return $result;

        foreach ($result as $indexby => $itemResult){
            $genClass = & new $className;
            PHPWS_Core::plugObject($genClass, $itemResult);

            if (isset($indexby))
                $items[$indexby] = $genClass;
            else
                $items[] = $genClass;
        }

        return $items;
    }

    function saveObject(&$object, $stripChar=FALSE, $autodetect_id=TRUE){

        if (!is_object($object))
            return PHPWS_Error::get(PHPWS_WRONG_TYPE, 'core', 'PHPWS_DB::saveObject', _('Type') . ': ' . gettype($object));

        $object_vars = get_object_vars($object);

        if (!is_array($object_vars))
            return PHPWS_Error::get(PHPWS_DB_NO_OBJ_VARS, 'core', 'PHPWS_DB::saveObject');

        foreach ($object_vars as $column => $value){
            if ($stripChar == TRUE)
                $column = substr($column, 1);
            if (!$this->isTableColumn($column))
                continue;

            if ($autodetect_id && ($column == 'id' && $value > 0)) {
                $this->addWhere('id', $value);
            }

            $this->addValue($column, $value);
        }

        if (isset($this->qwhere) ||
            ((isset($this->where) && count($this->where)))) {
            $result = $this->update();
        }
        else {
            $result = $this->insert();
            if (is_numeric($result)){
                if (array_key_exists('id', $object_vars))
                    $object->id = (int)$result;
                elseif (array_key_exists('_id', $object_vars))
                    $object->_id = (int)$result;
            }
        }

        $this->resetValues();

        return $result;
    }

  
    function allowed($value){
        if (!is_string($value))
            return FALSE;

        $reserved = array('ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'AUTO_INCREMENT', 'BDB',
                          'BERKELEYDB', 'BETWEEN', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'BTREE', 'BY', 'CASCADE',
                          'CASE', 'CHANGE', 'CHAR', 'CHARACTER', 'COLLATE', 'COLUMN', 'COLUMNS', 'CONSTRAINT', 'CREATE',
                          'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'DATABASE', 'DATABASES',
                          'DAY_HOUR', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DEFAULT',
                          'DELAYED', 'DELETE', 'DESC', 'DESCRIBE', 'DISTINCT', 'DISTINCTROW',
                          'DOUBLE', 'DROP', 'ELSE', 'ENCLOSED', 'ERRORS', 'ESCAPED', 'EXISTS', 'EXPLAIN', 'FIELDS',
                          'FLOAT', 'FOR', 'FOREIGN', 'FROM', 'FULLTEXT', 'FUNCTION', 'GEOMETRY', 'GRANT', 'GROUP',
                          'HASH', 'HAVING', 'HELP', 'HIGH_PRIORITY', 'HOUR_MINUTE', 'HOUR_SECOND',
                          'IF', 'IGNORE', 'IN', 'INDEX', 'INFILE', 'INNER', 'INNODB', 'INSERT', 'INT',
                          'INTEGER', 'INTERVAL', 'INTO', 'IS', 'JOIN', 'KEY', 'KEYS', 'KILL', 'LEADING',
                          'LEFT', 'LIKE', 'LIMIT', 'LINES', 'LOAD', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT',
                          'LOW_PRIORITY', 'MASTER_SERVER_ID', 'MATCH', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 
                          'MIDDLEINT', 'MINUTE_SECOND', 'MRG_MYISAM', 'NATURAL', 'NOT', 'NULL', 'NUMERIC', 'ON', 'OPTIMIZE',
                          'OPTION', 'OPTIONALLY', 'OR', 'ORDER', 'OUTER', 'OUTFILE', 'PRECISION', 'PRIMARY', 'PRIVILEGES',
                          'PROCEDURE', 'PURGE', 'READ', 'REAL', 'REFERENCES', 'REGEXP', 'RENAME', 'REPLACE', 'REQUIRE',
                          'RESTRICT', 'RETURNS', 'REVOKE', 'RIGHT', 'RLIKE', 'RTREE', 'SELECT', 'SET', 'SHOW',
                          'SMALLINT', 'SONAME', 'SPATIAL', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT',
                          'SSL', 'STARTING', 'STRAIGHT_JOIN', 'STRIPED', 'TABLE', 'TABLES', 'TERMINATED', 'THEN', 'TINYBLOB',
                          'TINYINT', 'TINYTEXT', 'TO', 'TRAILING', 'TYPES', 'UNION', 'UNIQUE', 'UNLOCK', 'UNSIGNED',
                          'UPDATE', 'USAGE', 'USE', 'USER_RESOURCES', 'USING', 'VALUES', 'VARBINARY', 'VARCHAR', 'VARYING',
                          'WARNINGS', 'WHEN', 'WHERE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL');

        if(in_array(strtoupper($value), $reserved))
            return FALSE;

        if(preg_match('/[^\w\*\.]/', $value)) 
            return FALSE;

        return TRUE;
    }



    function autoTrim($sql, $type){
        if (PEAR::isError($sql) || !is_array($sql))
            return $sql;

        if (!count($sql))
            return NULL;

        if ($GLOBALS['PEAR_DB']->phptype != 'pgsql')
            return $sql;

        switch ($type){
        case 'col':
            array_walk($sql, 'db_trim');
            break;

        default:
            array_walk($sql, 'db_trim');
            break;
        }

        return $sql;
    }

}

class PHPWS_DB_Where {
    var $table      = NULL;
    var $column     = NULL;
    var $value      = NULL;
    var $operator   = '=';
    var $conj       = 'AND';
    var $join       = FALSE;

    function setJoinTable($table)
    {
        $this->join_table = $table;
    }

    function setColumn($column)
    {
        $this->columns = $column;
    }

    function setOperator($operator)
    {
        if (empty($operator)) {
            return FALSE;
        }

        if (!PHPWS_DB::checkOperator($operator)) {
            return PHPWS_Error::get(PHPWS_DB_BAD_OP, 'core', 'PHPWS_DB::addWhere', _('DB Operator:') . $operator);
        }

        $this->operator = $operator;
    }

    function setJoin($join)
    {
        $this->join = (bool)$join;
    }

    function setValue($value)
    {
        $this->value = $value;
    }

    function setTable($table)
    {
        $this->table = $table;
    }

    function setConj($conj)
    {
        $conj = strtoupper($conj);
        if (empty($conj) || ($conj != 'OR' &&  $conj != 'AND')) {
            return FALSE;
        }

        $this->conj = $conj;
    }

    function getValue()
    {
        $value = $this->value;

        if (is_array($value)) {
            switch ($this->operator){
            case 'IN':
                foreach ($value as $temp_val) {
                    if ($temp_val != 'NULL') {
                        $temp_val_list[] = "'$temp_val'";
                    } else {
                        $temp_val_list[] = $temp_val;
                    }
                }
                $value = '(' . implode(', ', $temp_val_list) . ')';

                break;

            case 'BETWEEN':
                $value = sprintf("'{%s}' AND '{%s}'", $this->value[0], $this->$value[1]);
                break;
            }
            return $value;
        }

        if (!$this->join && $value != 'NULL') {
            return sprintf('\'%s\'', $value);
        }

        return $value;
    }

    function get()
    {
        $column = $this->table . '.' . $this->columns;
        $value = $this->getValue();
        $operator = &$this->operator;
        return sprintf('%s %s %s', $column, $operator, $value);
    }

}


function db_trim(&$value){
    if (PEAR::isError($value) || !isset($value))
        return;

    if (is_array($value)){
        array_walk($value, 'db_trim');
        return;
    }

    $value = rtrim($value);
}

function _add_tbl_prefix(&$val, $keynull, $prefix)
{
    $val = $prefix . $val;
}

?>
