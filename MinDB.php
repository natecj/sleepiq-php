<?php

class MinDB {

  private $mysqli = null;
  
  function __construct( $host, $user, $pass, $name ) {
    $this->open( $host, $user, $pass, $name );
  }
  
  function __destruct() {
    $this->close();
  }

  public function open( $host, $user, $pass, $name ) {
    if ( $this->mysqli )
      return;
    $this->mysqli = new mysqli( $host, $user, $pass, $name );
    if ( $this->mysqli->connect_error )
      die( 'Database connection error (' . $this->mysqli->connect_errno . ') '.$this->mysqli->connect_error );
  }

  public function close() {
    $this->mysqli->close();
    $this->mysqli = null;
  }

  public function isConnected() {
    return ( $this->mysqli ? true : false );
  }

  public function query( $query ) {
    if ( !$this->mysqli )
      $this->open();
    return $this->mysqli->query( $query );
  }

  public function escapeString( $string ) {
    if ( !$this->mysqli )
      $this->open();
    return $this->mysqli->escape_string( $string );
  }

  public function queryFetch( $query ) {
    $result = $this->query( $query );
    $return = null;
    if ( $result ) {
      $return = $result->fetch_assoc();
      $result->close();
    }
    return $return;
  }

  public function queryFetchAll( $query ) {
    $result = $this->query( $query );
    $return = array();
    if ( $result ) {
      while( $row = $result->fetch_object() )
        $return[] = (array)$row;
      $result->close();
    }
    return $return;
  }

  public function queryFetchVar( $query, $field ) {
    $result = $this->queryFetch( $query );
    if ( $result )
      return $result[ $field ];
    else
      return null;
  }
  
  public function exists( $table, $id_field, $id ) {
    $id     = $this->escapeString( $id );
    $record = $this->queryFetch("SELECT * FROM {$table} WHERE `{$id_field}` = '{$id}'");    
    return $record ? true : false;
  }
  public function exists_advanced( $table, $field_array ) {
    $where_array = [];
    foreach( $field_array as $field => $value )
      $where_array[] = "`{$field}` = '".$this->escapeString( $value )."'";
    $record = $this->queryFetch("SELECT * FROM {$table} WHERE ".implode( " AND ", $where_array ) );    
    return $record ? true : false;
  }

  public function insert( $table, $data ) {
    $table = $this->escapeString( $table );
		$columns = '';
		$values = '';
		foreach( $data as $key => $value ) {
      $value = $this->escapeString( $value );
			$columns .= " `$key`,";
			$values  .= " '$value',";
		}
		$columns = preg_replace( '/,$/', '', $columns );
		$values  = preg_replace( '/,$/', '', $values );
		$query_string = "INSERT INTO $table( $columns ) VALUES ( $values )";
    $query_result = $this->query( $query_string );
    return $this->mysqli->insert_id;
  }

	public function update( $table, $data, $id_field, $id ) {
    $setters = '';
		foreach( $data as $key => $value ) {
      $value = $this->escapeString( $value );
			$setters .= " `$key`='$value',";
		}
		$setters = preg_replace( '/,$/' , ' ' , $setters );
    $query_string = "UPDATE $table SET " . $setters . "WHERE `".$id_field."` = '".$id."'";
		return $this->query( $query_string );
	}

  public function isUniqueValue( $table, $field, $value ) {
    $table = $this->escapeString( $table );
    $field = $this->escapeString( $field );
    $value = $this->escapeString( $value );
    $count = $this->queryFetchVar( "SELECT COUNT(`{$field}`) AS count FROM {$table} WHERE `{$field}`='{$value}'", "count" );
    return ( $count > 0 ? false : true );
  }

  public function mapArrayByField( $in_array, $field ) {
    $out_array = array();
    foreach( $in_array as $values )
      $out_array[ $values[ $field ] ] = $values;
    return $out_array;
  }

}
